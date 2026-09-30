C:\xampp\htdocs\new app\whatsapp-clone\src\views\ChatPage.vue

<template>
  <ion-page class="chat-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-buttons slot="start">
          <ion-back-button default-href="/chats" />
        </ion-buttons>

```
    <ion-avatar slot="start" class="avatar-header">
      <div class="avatar-ring">
        <img :src="contacto.avatar" />
      </div>
    </ion-avatar>

    <ion-title class="header-title">{{ contacto.nombre }}</ion-title>

    <ion-button
      v-if="contacto.id && !llamadaActiva"
      slot="end"
      fill="clear"
      class="header-action"
      @click="iniciarLlamada"
    >
      <ion-icon :icon="callOutline" />
    </ion-button>

    <ion-button
      v-if="!yaAgregado && contacto.id"
      slot="end"
      fill="clear"
      class="header-add"
      @click="agregarContacto"
    >
      Agregar
    </ion-button>
  </ion-toolbar>
</ion-header>

<ion-content class="chat-content" :fullscreen="true">
  <div class="ambient-glow glow-emerald"></div>
  <div class="ambient-glow glow-cyan"></div>

  <div class="mensajes">
    <div
      v-for="mensaje in mensajes"
      :key="mensaje.id"
      class="mensaje"
      :class="
        esMiMensaje(mensaje.remitente_id) ? 'mensaje-yo' : 'mensaje-otro'
      "
    >
      <div class="burbuja">
        <div class="texto">
          {{ mensaje.mensaje }}
        </div>

        <div class="hora">
          {{ formatearHora(mensaje.fecha_envio) }}
        </div>
      </div>
    </div>
  </div>
</ion-content>

<div v-if="llamadaActiva" class="llamada-activa">
  <div class="llamada-info">
    <div class="avatar-ring llamada-avatar-ring">
      <ion-avatar class="avatar-llamada">
        <img :src="contacto.avatar" />
      </ion-avatar>
    </div>

    <div>
      <div class="nombre-llamada">
        {{ contacto.nombre }}
      </div>

      <div class="estado-llamada">
        {{ estadoLlamada }}
      </div>

      <div
        v-if="estadoLlamada === 'Conectada'"
        class="duracion-llamada"
      >
        {{ formatearDuracion(duracionLlamada) }}
      </div>
    </div>
  </div>

  <ion-button
    class="boton-colgar"
    shape="round"
    @click="finalizarLlamadaActual"
  >
    <ion-icon :icon="callOutline" />
  </ion-button>
</div>

<div class="barra-mensaje">
  <ion-input
    v-model="nuevoMensaje"
    placeholder="Escribe un mensaje..."
    fill="outline"
    :clear-input="true"
    @keyup.enter="enviarMensaje"
  />

  <ion-button
    shape="round"
    class="boton-enviar"
    :disabled="!nuevoMensaje.trim()"
    @click="enviarMensaje"
  >
    <ion-icon :icon="sendOutline" />
  </ion-button>
</div>

<audio ref="audioRemoto" autoplay playsinline></audio>

<ion-alert
  :is-open="llamadaEntrante !== null"
  header="Llamada entrante"
  :message="mensajeLlamadaEntrante"
  :buttons="botonesLlamada"
  @didDismiss="cerrarLlamadaEntrante"
/>
```

  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonButtons,
  IonBackButton,
  IonAvatar,
  IonTitle,
  IonContent,
  IonInput,
  IonButton,
  IonIcon,
  IonAlert,
} from "@ionic/vue";
import { sendOutline, callOutline } from "ionicons/icons";

interface Mensaje {
  id: number;
  conversacion_id: number;
  remitente_id: number | string;
  mensaje: string;
  mensaje_tipo: string;
  fecha_envio: string;
  nombre: string;
  apellido: string;
}

interface Contacto {
  id: number;
  nombre: string;
  apellido: string;
  foto: string | null;
}

interface ContactoLista {
  contacto_id: number;
}

interface LlamadaEntrante {
  id: number;
  conversacion_id: number;
  llamante_id: number;
  receptor_id: number;
  tipo: string;
  estado: string;
  llamante_nombre: string;
  llamante_apellido: string;
  llamante_foto: string | null;
}

const route = useRoute();

const nuevoMensaje = ref("");
const mensajes = ref<Mensaje[]>([]);
const usuarioId = ref<number | null>(null);
const yaAgregado = ref(false);

const llamadaEntrante = ref<LlamadaEntrante | null>(null);
const llamadaActiva = ref(false);
const estadoLlamada = ref("Conectando...");
const duracionLlamada = ref(0);

const llamadaId = ref<number | null>(null);
const peerConnection = ref<RTCPeerConnection | null>(null);
const localStream = ref<MediaStream | null>(null);
const remoteStream = ref<MediaStream | null>(null);
const audioRemoto = ref<HTMLAudioElement | null>(null);

let intervaloMensajes: ReturnType<typeof setInterval> | null = null;
let intervaloLlamadas: ReturnType<typeof setInterval> | null = null;
let intervaloWebRTC: ReturnType<typeof setInterval> | null = null;
let intervaloEstadoLlamada: ReturnType<typeof setInterval> | null = null;
let intervaloContador: ReturnType<typeof setInterval> | null = null;

let ultimoIceCandidateId = 0;

const contacto = ref({
  id: 0,
  nombre: "Chat",
  avatar: "https://i.pravatar.cc/150?img=12",
});

const esMiMensaje = (remitenteId: number | string) => {
  if (usuarioId.value === null) {
    return false;
  }

  return Number(remitenteId) === Number(usuarioId.value);
};

const cargarUsuario = () => {
  const usuarioGuardado = localStorage.getItem("usuario");

  if (!usuarioGuardado) {
    return;
  }

  const usuario = JSON.parse(usuarioGuardado);

  usuarioId.value = Number(usuario.id);
};

const verificarContacto = async (contactoId: number) => {
  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/contactos_controlador.php?task=obtenerContactos",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      console.error(resultado.error);
      return;
    }

    yaAgregado.value = resultado.contactos.some(
      (contacto: ContactoLista) =>
        Number(contacto.contacto_id) === Number(contactoId),
    );
  } catch (error) {
    console.error("Error al verificar contacto:", error);
  }
};

const cargarContacto = async () => {
  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const conversacionId = route.params.id;

    if (!conversacionId) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("conversacion_id", String(conversacionId));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/conversaciones_controlador.php?task=obtenerContacto",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      console.error(resultado.error);
      return;
    }

    const usuario: Contacto = resultado.contacto;

    contacto.value = {
      id: usuario.id,
      nombre: `${usuario.nombre} ${usuario.apellido}`,
      avatar: usuario.foto || "https://i.pravatar.cc/150?img=12",
    };

    await verificarContacto(usuario.id);
  } catch (error) {
    console.error("Error al cargar contacto:", error);
  }
};

const agregarContacto = async () => {
  if (!contacto.value.id) {
    return;
  }

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Sesión no válida");
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("contacto_id", contacto.value.id.toString());

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/contactos_controlador.php?task=agregarContacto",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      alert(resultado.error);
      return;
    }

    yaAgregado.value = true;
  } catch (error) {
    console.error("Error al agregar contacto:", error);
    alert("Error al agregar el contacto");
  }
};

const crearConexionWebRTC = async () => {
  try {
    if (peerConnection.value) {
      return;
    }

    localStream.value = await navigator.mediaDevices.getUserMedia({
      audio: true,
      video: false,
    });

    remoteStream.value = new MediaStream();

    peerConnection.value = new RTCPeerConnection({
      iceServers: [
        {
          urls: "stun:stun.l.google.com:19302",
        },
      ],
    });

    localStream.value.getTracks().forEach((track) => {
      peerConnection.value?.addTrack(track, localStream.value as MediaStream);
    });

    peerConnection.value.ontrack = (evento) => {
      if (!remoteStream.value) {
        remoteStream.value = new MediaStream();
      }

      evento.streams[0].getTracks().forEach((track) => {
        remoteStream.value?.addTrack(track);
      });

      if (audioRemoto.value && remoteStream.value) {
        audioRemoto.value.srcObject = remoteStream.value;

        audioRemoto.value.play().catch((error) => {
          console.error("Error al reproducir audio remoto:", error);
        });
      }
    };

    peerConnection.value.onicecandidate = async (evento) => {
      if (!evento.candidate || !llamadaId.value) {
        return;
      }

      const token = localStorage.getItem("token");

      if (!token) {
        return;
      }

      const datos = new URLSearchParams();

      datos.append("token", token);
      datos.append("llamada_id", String(llamadaId.value));
      datos.append("candidate", evento.candidate.candidate);
      datos.append("sdp_mid", evento.candidate.sdpMid || "");

      if (evento.candidate.sdpMLineIndex !== null) {
        datos.append(
          "sdp_m_line_index",
          String(evento.candidate.sdpMLineIndex),
        );
      }

      try {
        await fetch(
          "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=guardarIceCandidate",
          {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: datos.toString(),
          },
        );
      } catch (error) {
        console.error("Error al guardar ICE candidate:", error);
      }
    };
  } catch (error) {
    console.error("Error al crear conexión WebRTC:", error);

    alert("No se pudo acceder al micrófono");

    cerrarConexionWebRTC();
  }
};

const guardarOferta = async (offer: RTCSessionDescriptionInit) => {
  const token = localStorage.getItem("token");

  if (!token || !llamadaId.value) {
    return false;
  }

  const datos = new URLSearchParams();

  datos.append("token", token);
  datos.append("llamada_id", String(llamadaId.value));
  datos.append("offer_sdp", JSON.stringify(offer));

  const respuesta = await fetch(
    "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=guardarOferta",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: datos.toString(),
    },
  );

  const resultado = await respuesta.json();

  if (!resultado.ok) {
    console.error(resultado.error);
    return false;
  }

  return true;
};

const obtenerOferta = async () => {
  const token = localStorage.getItem("token");

  if (!token || !llamadaId.value) {
    return null;
  }

  const datos = new URLSearchParams();

  datos.append("token", token);
  datos.append("llamada_id", String(llamadaId.value));

  const respuesta = await fetch(
    "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=obtenerOferta",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: datos.toString(),
    },
  );

  const resultado = await respuesta.json();

  if (!resultado.ok || !resultado.offer_sdp) {
    return null;
  }

  return JSON.parse(resultado.offer_sdp);
};

const guardarRespuesta = async (answer: RTCSessionDescriptionInit) => {
  const token = localStorage.getItem("token");

  if (!token || !llamadaId.value) {
    return false;
  }

  const datos = new URLSearchParams();

  datos.append("token", token);
  datos.append("llamada_id", String(llamadaId.value));
  datos.append("answer_sdp", JSON.stringify(answer));

  const respuesta = await fetch(
    "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=guardarRespuesta",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: datos.toString(),
    },
  );

  const resultado = await respuesta.json();

  if (!resultado.ok) {
    console.error(resultado.error);
    return false;
  }

  return true;
};

const obtenerRespuesta = async () => {
  const token = localStorage.getItem("token");

  if (!token || !llamadaId.value) {
    return null;
  }

  const datos = new URLSearchParams();

  datos.append("token", token);
  datos.append("llamada_id", String(llamadaId.value));

  const respuesta = await fetch(
    "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=obtenerRespuesta",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: datos.toString(),
    },
  );

  const resultado = await respuesta.json();

  if (!resultado.ok || !resultado.answer_sdp) {
    return null;
  }

  return JSON.parse(resultado.answer_sdp);
};

const obtenerIceCandidatesWebRTC = async () => {
  if (!llamadaId.value || !peerConnection.value) {
    return;
  }

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("llamada_id", String(llamadaId.value));
    datos.append("ultimo_id", String(ultimoIceCandidateId));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=obtenerIceCandidates",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      return;
    }

    for (const candidato of resultado.candidates) {
      try {
        await peerConnection.value.addIceCandidate(
          new RTCIceCandidate({
            candidate: candidato.candidate,
            sdpMid: candidato.sdp_mid,
            sdpMLineIndex: candidato.sdp_m_line_index,
          }),
        );

        ultimoIceCandidateId = Number(candidato.id);
      } catch (error) {
        console.error("Error al agregar ICE candidate:", error);
      }
    }
  } catch (error) {
    console.error("Error al obtener ICE candidates:", error);
  }
};

const iniciarPollingWebRTC = () => {
  if (intervaloWebRTC) {
    clearInterval(intervaloWebRTC);
  }

  intervaloWebRTC = setInterval(() => {
    obtenerIceCandidatesWebRTC();
  }, 1000);
};

const esperarRespuesta = async () => {
  const respuesta = await obtenerRespuesta();

  if (!respuesta || !peerConnection.value) {
    return;
  }

  try {
    await peerConnection.value.setRemoteDescription(
      new RTCSessionDescription(respuesta),
    );

    estadoLlamada.value = "Conectada";

    iniciarContador();

    if (intervaloWebRTC) {
      clearInterval(intervaloWebRTC);
      intervaloWebRTC = null;
    }

    iniciarPollingWebRTC();
  } catch (error) {
    console.error("Error al establecer respuesta WebRTC:", error);
  }
};

const obtenerEstadoLlamada = async () => {
  if (!llamadaId.value || !llamadaActiva.value) {
    return;
  }

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("llamada_id", String(llamadaId.value));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=obtenerEstadoLlamada",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      return;
    }

    if (
      resultado.estado === "finalizada" ||
      resultado.estado === "rechazada" ||
      resultado.estado === "cancelada" ||
      resultado.estado === "sin_respuesta"
    ) {
      detenerContador();

      cerrarConexionWebRTC();

      llamadaActiva.value = false;
      estadoLlamada.value = "Conectando...";

      detenerPollingEstadoLlamada();
    }
  } catch (error) {
    console.error("Error al obtener estado de llamada:", error);
  }
};

const iniciarPollingEstadoLlamada = () => {
  detenerPollingEstadoLlamada();

  intervaloEstadoLlamada = setInterval(() => {
    obtenerEstadoLlamada();
  }, 1000);
};

const detenerPollingEstadoLlamada = () => {
  if (intervaloEstadoLlamada) {
    clearInterval(intervaloEstadoLlamada);
    intervaloEstadoLlamada = null;
  }
};

const iniciarContador = () => {
  detenerContador();

  duracionLlamada.value = 0;

  intervaloContador = setInterval(() => {
    if (llamadaActiva.value && estadoLlamada.value === "Conectada") {
      duracionLlamada.value++;
    }
  }, 1000);
};

const detenerContador = () => {
  if (intervaloContador) {
    clearInterval(intervaloContador);
    intervaloContador = null;
  }

  duracionLlamada.value = 0;
};

const formatearDuracion = (segundos: number) => {
  const minutos = Math.floor(segundos / 60);
  const segundosRestantes = segundos % 60;

  return `${String(minutos).padStart(2, "0")}:${String(
    segundosRestantes,
  ).padStart(2, "0")}`;
};

const iniciarLlamada = async () => {
  try {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Sesión no válida");
      return;
    }

    const conversacionId = route.params.id;

    if (!conversacionId || !contacto.value.id) {
      alert("No se encontró la conversación");
      return;
    }

    estadoLlamada.value = "Llamando...";
    llamadaActiva.value = true;
    duracionLlamada.value = 0;

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("conversacion_id", String(conversacionId));
    datos.append("receptor_id", String(contacto.value.id));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=iniciarLlamada",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      llamadaActiva.value = false;
      alert(resultado.error);
      return;
    }

    llamadaId.value = Number(resultado.llamada_id);

    iniciarPollingEstadoLlamada();

    await crearConexionWebRTC();

    if (!peerConnection.value) {
      llamadaActiva.value = false;
      detenerPollingEstadoLlamada();
      return;
    }

    const offer = await peerConnection.value.createOffer();

    await peerConnection.value.setLocalDescription(offer);

    const guardada = await guardarOferta(offer);

    if (!guardada) {
      cerrarConexionWebRTC();
      llamadaActiva.value = false;
      detenerPollingEstadoLlamada();
      alert("No se pudo guardar la oferta");
      return;
    }

    iniciarPollingWebRTC();

    const intervaloRespuesta = setInterval(async () => {
      if (!llamadaActiva.value || !llamadaId.value) {
        clearInterval(intervaloRespuesta);
        return;
      }

      const respuesta = await obtenerRespuesta();

      if (!respuesta || !peerConnection.value) {
        return;
      }

      clearInterval(intervaloRespuesta);

      try {
        await peerConnection.value.setRemoteDescription(
          new RTCSessionDescription(respuesta),
        );

        estadoLlamada.value = "Conectada";

        iniciarContador();
      } catch (error) {
        console.error("Error al establecer respuesta WebRTC:", error);
      }
    }, 1000);
  } catch (error) {
    console.error("Error al iniciar llamada:", error);
    cerrarConexionWebRTC();
    llamadaActiva.value = false;
    detenerPollingEstadoLlamada();
    detenerContador();
    alert("Error al iniciar la llamada");
  }
};

const aceptarLlamada = async () => {
  if (!llamadaEntrante.value) {
    return;
  }

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Sesión no válida");
      return;
    }

    llamadaId.value = Number(llamadaEntrante.value.id);
    llamadaActiva.value = true;
    estadoLlamada.value = "Aceptando...";
    duracionLlamada.value = 0;

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("llamada_id", String(llamadaEntrante.value.id));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=aceptarLlamada",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      llamadaActiva.value = false;
      llamadaId.value = null;
      alert(resultado.error);
      return;
    }

    llamadaEntrante.value = null;

    iniciarPollingEstadoLlamada();

    await crearConexionWebRTC();

    if (!peerConnection.value) {
      llamadaActiva.value = false;
      detenerPollingEstadoLlamada();
      return;
    }

    let oferta: RTCSessionDescriptionInit | null = null;

    for (let intento = 0; intento < 20; intento++) {
      oferta = await obtenerOferta();

      if (oferta) {
        break;
      }

      await new Promise((resolve) => setTimeout(resolve, 500));
    }

    if (!oferta) {
      alert("No se recibió la oferta de la llamada");
      cerrarConexionWebRTC();
      llamadaActiva.value = false;
      detenerPollingEstadoLlamada();
      return;
    }

    await peerConnection.value.setRemoteDescription(
      new RTCSessionDescription(oferta),
    );

    const answer = await peerConnection.value.createAnswer();

    await peerConnection.value.setLocalDescription(answer);

    const guardada = await guardarRespuesta(answer);

    if (!guardada) {
      cerrarConexionWebRTC();
      llamadaActiva.value = false;
      detenerPollingEstadoLlamada();
      alert("No se pudo guardar la respuesta");
      return;
    }

    estadoLlamada.value = "Conectada";

    iniciarContador();

    iniciarPollingWebRTC();
  } catch (error) {
    console.error("Error al aceptar llamada:", error);
    cerrarConexionWebRTC();
    llamadaActiva.value = false;
    detenerPollingEstadoLlamada();
    detenerContador();
    alert("Error al aceptar la llamada");
  }
};

const rechazarLlamada = async () => {
  if (!llamadaEntrante.value) {
    return;
  }

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Sesión no válida");
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("llamada_id", String(llamadaEntrante.value.id));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=rechazarLlamada",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      alert(resultado.error);
      return;
    }

    llamadaEntrante.value = null;
  } catch (error) {
    console.error("Error al rechazar llamada:", error);
    alert("Error al rechazar la llamada");
  }
};

const finalizarLlamadaActual = async () => {
  if (!llamadaId.value) {
    cerrarConexionWebRTC();
    llamadaActiva.value = false;
    detenerPollingEstadoLlamada();
    detenerContador();
    return;
  }

  const idLlamada = llamadaId.value;

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("llamada_id", String(idLlamada));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=finalizarLlamada",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      console.error(resultado.error);
    }
  } catch (error) {
    console.error("Error al finalizar llamada:", error);
  }

  detenerPollingEstadoLlamada();
  detenerContador();

  cerrarConexionWebRTC();
  llamadaActiva.value = false;
  estadoLlamada.value = "Conectando...";
};

const cerrarConexionWebRTC = () => {
  if (intervaloWebRTC) {
    clearInterval(intervaloWebRTC);
    intervaloWebRTC = null;
  }

  if (peerConnection.value) {
    peerConnection.value.ontrack = null;
    peerConnection.value.onicecandidate = null;
    peerConnection.value.close();
    peerConnection.value = null;
  }

  if (localStream.value) {
    localStream.value.getTracks().forEach((track) => {
      track.stop();
    });

    localStream.value = null;
  }

  if (audioRemoto.value) {
    audioRemoto.value.srcObject = null;
  }

  remoteStream.value = null;
  llamadaId.value = null;
  ultimoIceCandidateId = 0;
};

const revisarLlamadaEntrante = async () => {
  try {
    const token = localStorage.getItem("token");

    if (!token || llamadaEntrante.value || llamadaActiva.value) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/llamadas_controlador.php?task=obtenerLlamadaEntrante",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      return;
    }

    if (resultado.llamada) {
      llamadaEntrante.value = resultado.llamada;
    }
  } catch (error) {
    console.error("Error al revisar llamada entrante:", error);
  }
};

const cerrarLlamadaEntrante = () => {
  llamadaEntrante.value = null;
};

const mensajeLlamadaEntrante = () => {
  if (!llamadaEntrante.value) {
    return "";
  }

  return `${llamadaEntrante.value.llamante_nombre} ${llamadaEntrante.value.llamante_apellido} te está llamando`;
};

const botonesLlamada = [
  {
    text: "Rechazar",
    role: "cancel",
    handler: () => {
      rechazarLlamada();
    },
  },
  {
    text: "Aceptar",
    handler: () => {
      aceptarLlamada();
    },
  },
];

const cargarMensajes = async () => {
  try {
    const token = localStorage.getItem("token");

    if (!token) {
      return;
    }

    const conversacionId = route.params.id;

    if (!conversacionId) {
      return;
    }

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("conversacion_id", String(conversacionId));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/mensajes_controlador.php?task=obtenerMensajes",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      console.error(resultado.error);
      return;
    }

    mensajes.value = resultado.mensajes;
  } catch (error) {
    console.error("Error al cargar mensajes:", error);
  }
};

const cargarConversacion = async () => {
  contacto.value = {
    id: 0,
    nombre: "Chat",
    avatar: "https://i.pravatar.cc/150?img=12",
  };

  mensajes.value = [];
  yaAgregado.value = false;

  await cargarContacto();
  await cargarMensajes();
};

const enviarMensaje = async () => {
  const texto = nuevoMensaje.value.trim();

  if (!texto) {
    return;
  }

  const token = localStorage.getItem("token");

  if (!token) {
    alert("Sesión no válida");
    return;
  }

  const conversacionId = route.params.id;

  if (!conversacionId) {
    alert("No se encontró la conversación");
    return;
  }

  try {
    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("conversacion_id", String(conversacionId));
    datos.append("mensaje", texto);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/mensajes_controlador.php?task=enviarMensaje",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      alert(resultado.error);
      return;
    }

    nuevoMensaje.value = "";

    await cargarMensajes();
  } catch (error) {
    console.error("Error al enviar mensaje:", error);
    alert("Error al enviar el mensaje");
  }
};

const formatearHora = (fecha: string) => {
  return new Date(fecha).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
};

watch(
  () => route.params.id,
  async () => {
    detenerPollingEstadoLlamada();
    detenerContador();

    if (llamadaActiva.value) {
      await finalizarLlamadaActual();
    }

    await cargarConversacion();
  },
);

onMounted(() => {
  cargarUsuario();
  cargarConversacion();

  intervaloMensajes = setInterval(() => {
    cargarMensajes();
  }, 2000);

  intervaloLlamadas = setInterval(() => {
    revisarLlamadaEntrante();
  }, 2000);
});

onUnmounted(() => {
  if (intervaloMensajes) {
    clearInterval(intervaloMensajes);
    intervaloMensajes = null;
  }

  if (intervaloLlamadas) {
    clearInterval(intervaloLlamadas);
    intervaloLlamadas = null;
  }

  detenerPollingEstadoLlamada();
  detenerContador();

  cerrarConexionWebRTC();
});
</script>

<style scoped>
.chat-page {
  --background: #090d16;
  background-color: #090d16;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.chat-content {
  --background: #090d16;
  position: relative;
}

.header-transparent {
  background: transparent;
}

.toolbar-transparent {
  --background: #090d16;
  --border-width: 0;
  padding-top: 6px;
}

.header-title {
  color: #ffffff;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.header-action {
  --color: #10b981;
}

.header-add {
  --color: #10b981;
  font-weight: 700;
  text-transform: none;
}

.avatar-header {
  width: 38px;
  height: 38px;
  margin-left: 4px;
  margin-right: 8px;
}

.avatar-header .avatar-ring {
  width: 38px;
  height: 38px;
}

.avatar-header img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.ambient-glow {
  position: fixed;
  border-radius: 50%;
  filter: blur(90px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.35;
}

.glow-emerald {
  width: 300px;
  height: 300px;
  background: #10b981;
  top: -60px;
  right: -60px;
}

.glow-cyan {
  width: 260px;
  height: 260px;
  background: #06b6d4;
  bottom: 80px;
  left: -80px;
}

.mensajes {
  display: flex;
  flex-direction: column;
  gap: 5px;
  padding: 14px 12px 18px;
  min-height: 100%;
  position: relative;
  z-index: 1;
  background:
    radial-gradient(
      circle at 20% 20%,
      rgba(16, 185, 129, 0.07),
      transparent 30%
    ),
    radial-gradient(
      circle at 80% 80%,
      rgba(6, 182, 212, 0.07),
      transparent 30%
    ),
    #090d16;
}

.mensaje {
  display: flex;
  width: 100%;
  margin: 2px 0;
}

.mensaje-yo {
  justify-content: flex-end;
}

.mensaje-otro {
  justify-content: flex-start;
}

.burbuja {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 78%;
  padding: 8px 10px;
  border-radius: 16px;
  font-size: 15px;
  line-height: 20px;
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.07);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
}

.mensaje-yo .burbuja {
  background: rgba(16, 185, 129, 0.72);
  border-top-right-radius: 4px;
  border-color: rgba(16, 185, 129, 0.3);
}

.mensaje-otro .burbuja {
  background: rgba(18, 24, 38, 0.78);
  border-top-left-radius: 4px;
}

.texto {
  overflow-wrap: anywhere;
}

.hora {
  flex-shrink: 0;
  font-size: 10px;
  line-height: 14px;
  color: #d1fae5;
  white-space: nowrap;
}

.mensaje-otro .hora {
  color: #94a3b8;
}

.barra-mensaje {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: rgba(18, 24, 38, 0.92);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.barra-mensaje ion-input {
  flex: 1;
  --background: rgba(255, 255, 255, 0.05);
  --color: #ffffff;
  --placeholder-color: #64748b;
  --border-radius: 18px;
  --padding-start: 14px;
  --padding-end: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 18px;
}

.boton-enviar {
  --background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  --color: #ffffff;
  --box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
  --border-radius: 16px;
  width: 44px;
  height: 44px;
}

.llamada-activa {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: rgba(18, 24, 38, 0.94);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.llamada-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.llamada-avatar-ring {
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  box-shadow: 0 0 14px rgba(16, 185, 129, 0.35);
}

.avatar-llamada {
  width: 42px;
  height: 42px;
}

.avatar-llamada img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.nombre-llamada {
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
}

.estado-llamada {
  color: #8a99ad;
  font-size: 13px;
}

.duracion-llamada {
  color: #ffffff;
  font-size: 13px;
  margin-top: 2px;
}

.boton-colgar {
  --background: #dc2626;
  --color: #ffffff;
  --box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
}

.avatar-ring {
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}

ion-back-button {
  --color: #ffffff;
}
</style>
