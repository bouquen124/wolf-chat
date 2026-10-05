<template>
  <ion-page class="chat-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-buttons slot="start">
          <ion-back-button default-href="/chats" />
        </ion-buttons>

        <button
          slot="start"
          class="chat-header-perfil"
          aria-label="Ver perfil del contacto"
          @click="abrirPerfilContacto"
        >
          <ion-avatar class="avatar-header">
            <div class="avatar-ring">
              <img v-if="contacto.avatar" :src="contacto.avatar" />
            </div>
          </ion-avatar>
          <span class="header-titulos">
            <span class="header-title">{{ contacto.nombre }}</span>
            <small v-if="contacto.enLinea" class="presencia-header presencia-header-online">En línea</small>
            <small v-else-if="contacto.ultimaConexion" class="presencia-header">
              Últ. vez {{ formatearUltimaConexion(contacto.ultimaConexion) }}
            </small>
          </span>
        </button>

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

    <PerfilContactoModal
      :visible="mostrarPerfilContacto"
      :perfil="perfilContacto"
      @cerrar="mostrarPerfilContacto = false"
    />

    <ion-content ref="contenido" class="chat-content" :fullscreen="true">
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

              <span
                v-if="esMiMensaje(mensaje.remitente_id)"
                class="estado-mensaje"
                :class="{
                  'mensaje-leido': mensaje.estado_mensaje === 'leido',
                }"
              >
                {{
                  mensaje.estado_mensaje === "leido" ||
                  mensaje.estado_mensaje === "entregado"
                    ? "✓✓"
                    : "✓"
                }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </ion-content>

    <div v-if="llamadaActiva" class="llamada-activa">
      <div class="llamada-info">
        <div class="avatar-ring llamada-avatar-ring">
          <ion-avatar class="avatar-llamada">
            <img v-if="contacto.avatar" :src="contacto.avatar" />
          </ion-avatar>
        </div>

        <div>
          <div class="nombre-llamada">
            {{ contacto.nombre }}
          </div>

          <div class="estado-llamada">
            {{ estadoLlamada }}
          </div>

          <div v-if="estadoLlamada === 'Conectada'" class="duracion-llamada">
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
        maxlength="4000"
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
      :message="mensajeLlamadaEntrante()"
      :buttons="botonesLlamada"
      @didDismiss="cerrarLlamadaEntrante"
    />
  </ion-page>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonButtons,
  IonBackButton,
  IonAvatar,
  IonContent,
  IonInput,
  IonButton,
  IonIcon,
  IonAlert,
  onIonViewDidLeave,
  onIonViewWillEnter,
} from "@ionic/vue";
import { sendOutline, callOutline } from "ionicons/icons";
import PerfilContactoModal from "../components/PerfilContactoModal.vue";

interface Mensaje {
  id: number;
  conversacion_id: number;
  remitente_id: number | string;
  mensaje: string;
  mensaje_tipo: string;
  fecha_envio: string;
  nombre: string;
  apellido: string;
  estado_mensaje: "enviado" | "entregado" | "leido" | null;
}

interface Contacto {
  id: number;
  nombre: string;
  apellido: string;
  foto: string | null;
  descripcion?: string | null;
  telefono?: string | null;
  ultima_conexion?: string | null;
  en_linea?: boolean;
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

// Referencia al ion-content para controlar el scroll
const contenido = ref<InstanceType<typeof IonContent> | null>(null);

let intervaloMensajes: ReturnType<typeof setInterval> | null = null;
let intervaloLlamadas: ReturnType<typeof setInterval> | null = null;
let intervaloWebRTC: ReturnType<typeof setInterval> | null = null;
let intervaloEstadoLlamada: ReturnType<typeof setInterval> | null = null;
let intervaloContador: ReturnType<typeof setInterval> | null = null;

let ultimoIceCandidateId = 0;
let cargandoIce = false;

// Control de mensajes / scroll
let primeraCargaMensajes = true;
let cargandoMensajes = false;

const contacto = ref({
  id: 0,
  nombre: "Chat",
  avatar: "",
  descripcion: "",
  telefono: "",
  ultimaConexion: null as string | null,
  enLinea: false,
});
const mostrarPerfilContacto = ref(false);
const formatearUltimaConexion = (valor: string) => {
  const fecha = new Date(valor.replace(" ", "T"));
  if (Number.isNaN(fecha.getTime())) return valor;
  return new Intl.DateTimeFormat("es-MX", { dateStyle: "short", timeStyle: "short" }).format(fecha);
};
const perfilContacto = computed(() => ({
  id: contacto.value.id,
  nombre: contacto.value.nombre,
  foto: contacto.value.avatar || null,
  descripcion: contacto.value.descripcion,
  telefono: contacto.value.telefono,
  ultima_conexion: contacto.value.ultimaConexion,
  en_linea: contacto.value.enLinea,
}));

/* ===================== SCROLL ===================== */

const scrollAlFinal = async (duracion = 0) => {
  await nextTick();

  const el = contenido.value?.$el as
    | (HTMLElement & { scrollToBottom?: (ms?: number) => Promise<void> })
    | undefined;

  if (!el || !el.scrollToBottom) {
    return;
  }

  await el.scrollToBottom(duracion);

  // Segundo intento por si el layout tardó en pintar los mensajes
  setTimeout(() => {
    el.scrollToBottom?.(0);
  }, 80);
};

const estaCercaDelFinal = async () => {
  const el = contenido.value?.$el as
    | (HTMLElement & { getScrollElement?: () => Promise<HTMLElement> })
    | undefined;

  if (!el || !el.getScrollElement) {
    return true;
  }

  const scrollEl = await el.getScrollElement();

  const distancia =
    scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight;

  return distancia < 150;
};

/* ===================== USUARIO / CONTACTO ===================== */

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
      nombre: `${usuario.nombre || ""} ${usuario.apellido || ""}`.trim(),
      avatar: usuario.foto || "",
      descripcion: usuario.descripcion || "",
      telefono: usuario.telefono || "",
      ultimaConexion: usuario.ultima_conexion || null,
      enLinea: Boolean(usuario.en_linea),
    };

    await verificarContacto(usuario.id);
  } catch (error) {
    console.error("Error al cargar contacto:", error);
  }
};

const abrirPerfilContacto = async () => {
  await cargarContacto();
  mostrarPerfilContacto.value = true;
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

/* ===================== WEBRTC ===================== */

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
  if (!llamadaId.value || !peerConnection.value || cargandoIce) {
    return;
  }

  // Sin remoteDescription, addIceCandidate falla. Los candidatos se quedan
  // en el servidor y se leen en cuanto ya exista.
  if (!peerConnection.value.remoteDescription) {
    return;
  }

  cargandoIce = true;

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
        cache: "no-store",
      },
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      return;
    }

    for (const candidato of resultado.candidates || []) {
      const pc = peerConnection.value;

      // La llamada pudo cerrarse mientras se procesaba la lista
      if (!pc || !pc.remoteDescription) {
        return;
      }

      try {
        await pc.addIceCandidate(
          new RTCIceCandidate({
            candidate: candidato.candidate,
            sdpMid: candidato.sdp_mid,
            sdpMLineIndex:
              candidato.sdp_m_line_index === null ||
              candidato.sdp_m_line_index === undefined
                ? null
                : Number(candidato.sdp_m_line_index),
          }),
        );
      } catch (error) {
        console.error("Error al agregar ICE candidate:", error);
      }

      ultimoIceCandidateId = Number(candidato.id);
    }
  } catch (error) {
    console.error("Error al obtener ICE candidates:", error);
  } finally {
    cargandoIce = false;
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
  cargandoIce = false;
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

/* ===================== MENSAJES ===================== */

const cargarMensajes = async (forzarScroll = false) => {
  // Evita que se encimen peticiones si el servidor tarda más de 2 s
  if (cargandoMensajes) {
    return;
  }

  cargandoMensajes = true;

  const idPeticion = String(route.params.id || "");

  try {
    const token = localStorage.getItem("token");

    if (!token || !idPeticion) {
      return;
    }

    const ultimoId =
      mensajes.value.length > 0
        ? mensajes.value[mensajes.value.length - 1].id
        : 0;

    // Mis mensajes que todavía no están en "leido" (para refrescar sus palomitas)
    const pendientes = mensajes.value
      .filter(
        (m) => esMiMensaje(m.remitente_id) && m.estado_mensaje !== "leido",
      )
      .slice(-100)
      .map((m) => m.id)
      .join(",");

    const datos = new URLSearchParams();

    datos.append("token", token);
    datos.append("conversacion_id", idPeticion);
    datos.append("ultimo_id", String(ultimoId));
    datos.append("pendientes", pendientes);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/mensajes_controlador.php?task=obtenerMensajes",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
        cache: "no-store",
      },
    );

    const resultado = await respuesta.json();

    // Si cambiaste de chat mientras llegaba la respuesta, se descarta
    if (idPeticion !== String(route.params.id || "")) {
      return;
    }

    if (!resultado.ok) {
      console.error(resultado.error);
      return;
    }

    const nuevos: Mensaje[] = (resultado.mensajes || []).filter(
      (m: Mensaje) => m.id > ultimoId,
    );
    const estados: { id: number; estado_mensaje: Mensaje["estado_mensaje"] }[] =
      resultado.estados || [];

    // Se mide ANTES de actualizar para saber si ya estabas abajo
    const estabaAbajo = await estaCercaDelFinal();

    if (nuevos.length > 0) {
      mensajes.value = [...mensajes.value, ...nuevos];
    }

    if (estados.length > 0) {
      const mapa = new Map(
        estados.map((e) => [Number(e.id), e.estado_mensaje]),
      );

      mensajes.value = mensajes.value.map((m) =>
        mapa.has(m.id) ? { ...m, estado_mensaje: mapa.get(m.id) ?? null } : m,
      );
    }

    if (primeraCargaMensajes) {
      primeraCargaMensajes = false;
      await scrollAlFinal(0);
      return;
    }

    if (forzarScroll || (nuevos.length > 0 && estabaAbajo)) {
      await scrollAlFinal(200);
    }
  } catch (error) {
    console.error("Error al cargar mensajes:", error);
  } finally {
    cargandoMensajes = false;
  }
};

const cargarConversacion = async () => {
  contacto.value = {
    id: 0,
    nombre: "Chat",
    avatar: "",
    descripcion: "",
  };

  mensajes.value = [];
  yaAgregado.value = false;
  primeraCargaMensajes = true;
  cargandoMensajes = false; // por si quedó una petición del chat anterior

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

    // Si el polling está a medio request, espera a que termine
    while (cargandoMensajes) {
      await new Promise((resolve) => setTimeout(resolve, 50));
    }

    // Al enviar, siempre baja al último mensaje
    await cargarMensajes(true);
  } catch (error) {
    console.error("Error al enviar mensaje:", error);
    alert("Error al enviar el mensaje");
  }
};

const formatearHora = (fecha: string) => {
  return new Date(fecha.replace(" ", "T")).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
};

/* ===================== CICLO DE VIDA ===================== */

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

onIonViewWillEnter(() => {
  cargarUsuario();
  cargarConversacion();

  if (!intervaloMensajes) {
    intervaloMensajes = setInterval(() => {
      cargarMensajes();
    }, 2000);
  }

  if (!intervaloLlamadas) {
    intervaloLlamadas = setInterval(() => {
      revisarLlamadaEntrante();
    }, 2000);
  }
});

onIonViewDidLeave(() => {
  // La página puede quedar montada en ion-router-outlet; detén el polling
  // para que un chat oculto no consulte y marque mensajes como leídos.
  if (intervaloMensajes) {
    clearInterval(intervaloMensajes);
    intervaloMensajes = null;
  }

  if (intervaloLlamadas) {
    clearInterval(intervaloLlamadas);
    intervaloLlamadas = null;
  }
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
  font-family:
    -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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

.chat-header-perfil {
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 0;
  color: inherit;
  background: transparent;
  border: 0;
  cursor: pointer;
}

.header-title {
  overflow: hidden;
  max-width: 46vw;
  color: #ffffff;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-titulos {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
}

.presencia-header {
  max-width: 48vw;
  overflow: hidden;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.presencia-header-online {
  color: #34d399;
}

.header-action {
  --color: var(--app-accent, #10b981);
}

.header-add {
  --color: var(--app-accent, #10b981);
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
  background: var(--app-accent, #10b981);
  top: -60px;
  right: -60px;
}

.glow-cyan {
  width: 260px;
  height: 260px;
  background: var(--app-accent-secondary, #06b6d4);
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
  background: var(
    --app-chat-wallpaper,
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
    #090d16
  );
  background-size: cover;
  background-position: center;
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

.estado-mensaje {
  margin-left: 3px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: -1px;
  color: #d1fae5;
}

.estado-mensaje.mensaje-leido {
  color: var(--app-accent-secondary, #06b6d4);
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
  --background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
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
  background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
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
  background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}

ion-back-button {
  --color: #ffffff;
}
</style>
