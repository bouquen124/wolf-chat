<template>
  <ion-page class="chat-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-buttons slot="start">
          <ion-back-button default-href="/chats" />
        </ion-buttons>

        <button slot="start" class="chat-header-perfil" aria-label="Ver perfil del contacto"
          @click="abrirPerfilContacto">
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

        <ion-button v-if="contacto.id && !llamadaActiva" slot="end" fill="clear" class="header-action"
          @click="iniciarLlamada">
          <ion-icon :icon="callOutline" />
        </ion-button>

        <ion-button v-if="!yaAgregado && contacto.id" slot="end" fill="clear" class="header-add"
          @click="agregarContacto">
          Agregar
        </ion-button>
      </ion-toolbar>
    </ion-header>

    <PerfilContactoModal :visible="mostrarPerfilContacto" :perfil="perfilContacto"
      @cerrar="mostrarPerfilContacto = false" />

    <ion-content ref="contenido" class="chat-content" :fullscreen="true">
      <div class="ambient-glow glow-emerald"></div>
      <div class="ambient-glow glow-cyan"></div>

      <div class="mensajes">
        <div v-for="mensaje in mensajes" :key="mensaje.id" class="mensaje" :class="esMiMensaje(mensaje.remitente_id) ? 'mensaje-yo' : 'mensaje-otro'
          ">
          <div class="burbuja"
            :class="{ 'burbuja-media': mensaje.mensaje_tipo === 'imagen' || mensaje.mensaje_tipo === 'audio' }">
            <img v-if="mensaje.mensaje_tipo === 'imagen' && mensaje.archivo_local_url" class="media-imagen"
              :src="mensaje.archivo_local_url" :alt="mensaje.archivo_nombre || 'Imagen enviada'" />
            <audio v-else-if="mensaje.mensaje_tipo === 'audio' && mensaje.archivo_local_url"
              class="audio-elemento-oculto" :ref="(elemento) => registrarAudio(mensaje.id, elemento)"
              :src="mensaje.archivo_local_url" preload="metadata" @timeupdate="actualizarProgresoAudio(mensaje.id)"
              @loadedmetadata="resolverDuracionAudio(mensaje.id)" @durationchange="resolverDuracionAudio(mensaje.id)"
              @ended="terminoAudio(mensaje.id)" />
            <div v-if="mensaje.mensaje_tipo === 'audio' && mensaje.archivo_local_url" class="reproductor-audio">
              <ion-button class="audio-play" fill="clear"
                :aria-label="audioReproduciendo === mensaje.id ? 'Pausar audio' : 'Reproducir audio'"
                @click.stop="alternarReproduccionAudio(mensaje.id)">
                <ion-icon :icon="audioReproduciendo === mensaje.id ? pauseOutline : playOutline" />
              </ion-button>
              <div class="audio-detalles">
                <div class="onda-audio" role="slider" aria-label="Progreso del audio"
                  @click.stop="buscarAudio($event, mensaje.id)">
                  <i v-for="(altura, indice) in ondaAudio" :key="indice"
                    :class="{ activa: indice / ondaAudio.length <= progresoAudio(mensaje.id) }"
                    :style="{ height: `${altura}px` }"></i>
                </div>
                <div class="audio-tiempo">
                  <span>{{ tiempoAudio(mensaje.id) }}</span>
                  <span>{{ duracionAudio(mensaje.id) }}</span>
                </div>
              </div>
            </div>
            <div v-else-if="mensaje.mensaje_tipo !== 'imagen' && mensaje.mensaje_tipo !== 'audio'" class="texto">{{
              mensaje.mensaje }}</div>

            <div class="hora">
              {{ formatearHora(mensaje.fecha_envio) }}

              <span v-if="esMiMensaje(mensaje.remitente_id)" class="estado-mensaje" :class="{
                'mensaje-leido': mensaje.estado_mensaje === 'leido',
              }">
                {{
                  mensaje.estado_mensaje === "leido" ||
                    mensaje.estado_mensaje === "entregado"
                    ? "✓✓"
                    : "✓"
                }}
              </span>
            </div>
            <ion-button v-if="esMiMensaje(mensaje.remitente_id) && mensaje.mensaje_tipo !== 'eliminado'"
              class="eliminar-mensaje" fill="clear" size="small" aria-label="Eliminar mensaje para todos"
              @click="eliminarMensajeParaTodos(mensaje)">
              <ion-icon :icon="trashOutline" />
            </ion-button>
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

      <ion-button class="boton-colgar" shape="round" @click="finalizarLlamadaActual">
        <ion-icon :icon="callOutline" />
      </ion-button>
    </div>

    <div class="barra-mensaje">
      <input ref="entradaAdjunto" class="entrada-adjunto" type="file"
        accept="image/jpeg,image/png,image/webp,image/gif,audio/*" @change="seleccionarAdjunto" />
      <div v-if="archivoPendiente" class="adjunto-pendiente">
        <span>{{ archivoPendiente.type.startsWith('audio/') ? 'Nota de voz lista para enviar' : archivoPendiente.name
        }}</span>
        <ion-button fill="clear" size="small" aria-label="Quitar archivo" @click="archivoPendiente = null">
          <ion-icon :icon="closeCircleOutline" />
        </ion-button>
      </div>
      <ion-button class="boton-adjunto" fill="clear" aria-label="Adjuntar foto o audio"
        :disabled="enviandoArchivo || grabandoAudio" @click="entradaAdjunto?.click()">
        <ion-icon :icon="attachOutline" />
      </ion-button>
      <ion-button class="boton-adjunto" :class="{ 'grabando-audio': grabandoAudio }" fill="clear"
        :aria-label="grabandoAudio ? 'Detener grabación' : 'Grabar audio'"
        :disabled="enviandoArchivo || Boolean(archivoPendiente)" @click="alternarGrabacionAudio">
        <ion-icon :icon="grabandoAudio ? stopOutline : micOutline" />
      </ion-button>
      <span v-if="grabandoAudio" class="estado-grabacion"><i></i> Grabando {{ formatoTiempoAudio(grabacionSegundos)
      }}</span>
      <ion-input v-model="nuevoMensaje" placeholder="Escribe un mensaje..." fill="outline" :maxlength="4000"
        :clear-input="true" :disabled="Boolean(archivoPendiente) || grabandoAudio || enviandoArchivo"
        @keyup.enter="enviarMensaje" />

      <ion-button shape="round" class="boton-enviar"
        :disabled="(!nuevoMensaje.trim() && !archivoPendiente) || enviandoArchivo || grabandoAudio"
        @click="archivoPendiente ? enviarArchivoChat() : enviarMensaje()">
        <ion-icon :icon="sendOutline" />
      </ion-button>
    </div>

    <audio ref="audioRemoto" autoplay playsinline></audio>

    <ion-alert :is-open="llamadaEntrante !== null" header="Llamada entrante" :message="mensajeLlamadaEntrante()"
      :buttons="botonesLlamada" @didDismiss="cerrarLlamadaEntrante" />
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
  alertController,
  onIonViewDidLeave,
  onIonViewWillEnter,
} from "@ionic/vue";
import { sendOutline, callOutline, attachOutline, micOutline, stopOutline, closeCircleOutline, trashOutline, playOutline, pauseOutline } from "ionicons/icons";
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
  archivo_url?: string | null;
  archivo_local_url?: string | null;
  archivo_mime?: string | null;
  archivo_nombre?: string | null;
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
const apiServidor = "https://eqtc5661rodrigo.tail4cd307.ts.net";

const nuevoMensaje = ref("");
const mensajes = ref<Mensaje[]>([]);
const entradaAdjunto = ref<HTMLInputElement | null>(null);
const archivoPendiente = ref<File | null>(null);
const enviandoArchivo = ref(false);
const grabandoAudio = ref(false);
const grabacionSegundos = ref(0);
const grabadorAudio = ref<MediaRecorder | null>(null);
let fragmentosAudio: Blob[] = [];
let intervaloGrabacion: ReturnType<typeof setInterval> | null = null;
const urlsAudioImagen = new Map<number, string>();
const reproductoresAudio = new Map<number, HTMLAudioElement>();
const ajustandoDuracionAudio = new Set<number>();
const audioReproduciendo = ref<number | null>(null);
const tiemposAudio = ref<Record<number, { actual: number; total: number }>>({});
const ondaAudio = Array.from({ length: 36 }, (_, indice) => 7 + ((indice * 13 + 9) % 13));
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/contactos_controlador.php?task=obtenerContactos",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=obtenerContacto",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/contactos_controlador.php?task=agregarContacto",
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
          "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=guardarIceCandidate",
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
    "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=guardarOferta",
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
    "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=obtenerOferta",
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
    "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=guardarRespuesta",
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
    "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=obtenerRespuesta",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=obtenerIceCandidates",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=obtenerEstadoLlamada",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=iniciarLlamada",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=aceptarLlamada",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=rechazarLlamada",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=finalizarLlamada",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/llamadas_controlador.php?task=obtenerLlamadaEntrante",
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

const abrirAlmacenAdjuntos = () => new Promise<IDBDatabase>((resolve, reject) => {
  if (!window.indexedDB) {
    reject(new Error("El almacenamiento local no está disponible en este dispositivo."));
    return;
  }
  // v2 clears previous cached responses that included whitespace before the binary file.
  const solicitud = indexedDB.open("miapp-adjuntos-chat", 2);
  solicitud.onupgradeneeded = (evento) => {
    const db = solicitud.result;
    if (!db.objectStoreNames.contains("archivos")) {
      db.createObjectStore("archivos", { keyPath: "id" });
    } else if ((evento as IDBVersionChangeEvent).oldVersion < 2) {
      solicitud.transaction?.objectStore("archivos").clear();
    }
  };
  solicitud.onsuccess = () => resolve(solicitud.result);
  solicitud.onerror = () => reject(solicitud.error || new Error("No se pudo abrir el almacenamiento local."));
});

const leerAdjuntoLocal = async (id: number): Promise<Blob | null> => {
  const db = await abrirAlmacenAdjuntos();
  return new Promise((resolve, reject) => {
    const tx = db.transaction("archivos", "readonly");
    const request = tx.objectStore("archivos").get(id);
    request.onsuccess = () => resolve(request.result?.blob || null);
    request.onerror = () => reject(request.error);
    tx.oncomplete = () => db.close();
    tx.onerror = () => db.close();
  });
};

const guardarAdjuntoLocal = async (id: number, blob: Blob, nombre: string) => {
  const db = await abrirAlmacenAdjuntos();
  return new Promise<void>((resolve, reject) => {
    const tx = db.transaction("archivos", "readwrite");
    tx.objectStore("archivos").put({ id, blob, nombre });
    tx.oncomplete = () => { db.close(); resolve(); };
    tx.onerror = () => { db.close(); reject(tx.error); };
  });
};

const quitarAdjuntoLocal = async (id: number) => {
  const db = await abrirAlmacenAdjuntos();
  return new Promise<void>((resolve, reject) => {
    const tx = db.transaction("archivos", "readwrite");
    tx.objectStore("archivos").delete(id);
    tx.oncomplete = () => { db.close(); resolve(); };
    tx.onerror = () => { db.close(); reject(tx.error); };
  });
};

const registrarAudio = (id: number, elemento: unknown) => {
  if (elemento && typeof (elemento as HTMLAudioElement).play === "function") {
    const audio = elemento as HTMLAudioElement;
    if (reproductoresAudio.get(Number(id)) !== audio) {
      audio.defaultPlaybackRate = 1;
      audio.playbackRate = 1;
      reproductoresAudio.set(Number(id), audio);
      audio.load();
    }
  } else {
    reproductoresAudio.delete(Number(id));
  }
};

const resolverDuracionAudio = (id: number) => {
  const clave = Number(id);
  const audio = reproductoresAudio.get(clave);
  if (!audio) return;
  actualizarProgresoAudio(clave);

  // Some WebM voice notes have no duration in their container metadata.
  // Seeking to the end makes Chromium/WebView calculate the real duration.
  if ((Number.isFinite(audio.duration) && audio.duration > 0) || ajustandoDuracionAudio.has(clave)) return;
  ajustandoDuracionAudio.add(clave);
  const terminarAjuste = () => {
    audio.removeEventListener("timeupdate", terminarAjuste);
    const finSeekable = audio.seekable.length
      ? audio.seekable.end(audio.seekable.length - 1)
      : 0;
    const duracion = Number.isFinite(audio.duration) && audio.duration > 0
      ? audio.duration
      : Number.isFinite(audio.currentTime) && audio.currentTime > 0
        ? audio.currentTime
        : finSeekable;
    try { audio.currentTime = 0; } catch { /* The media may still be loading. */ }
    tiemposAudio.value = {
      ...tiemposAudio.value,
      [clave]: { actual: 0, total: duracion },
    };
    ajustandoDuracionAudio.delete(clave);
  };
  audio.addEventListener("timeupdate", terminarAjuste, { once: true });
  try {
    audio.currentTime = Number.MAX_SAFE_INTEGER;
  } catch {
    audio.removeEventListener("timeupdate", terminarAjuste);
    ajustandoDuracionAudio.delete(clave);
  }
};

const alternarReproduccionAudio = async (id: number) => {
  const audio = reproductoresAudio.get(Number(id));
  if (!audio) return;
  if (audioReproduciendo.value === Number(id) && !audio.paused) {
    audio.pause();
    audioReproduciendo.value = null;
    return;
  }
  reproductoresAudio.forEach((otro, otroId) => {
    if (otroId !== Number(id)) otro.pause();
  });
  try {
    audio.defaultPlaybackRate = 1;
    audio.playbackRate = 1;
    await audio.play();
    audioReproduciendo.value = Number(id);
  } catch (error) {
    console.error("No se pudo reproducir la nota de voz:", error);
  }
};

const actualizarProgresoAudio = (id: number) => {
  const audio = reproductoresAudio.get(Number(id));
  if (!audio) return;
  const tiempoAnterior = tiemposAudio.value[Number(id)];
  const finSeekable = audio.seekable.length
    ? audio.seekable.end(audio.seekable.length - 1)
    : 0;
  const duracion = Number.isFinite(audio.duration) && audio.duration > 0
    ? audio.duration
    : tiempoAnterior?.total || finSeekable;
  tiemposAudio.value = {
    ...tiemposAudio.value,
    [Number(id)]: {
      actual: Number.isFinite(audio.currentTime) ? audio.currentTime : 0,
      total: duracion,
    },
  };
  if (audio.paused && audioReproduciendo.value === Number(id)) audioReproduciendo.value = null;
};

const terminoAudio = (id: number) => {
  if (audioReproduciendo.value === Number(id)) audioReproduciendo.value = null;
  actualizarProgresoAudio(id);
};

const progresoAudio = (id: number) => {
  const tiempo = tiemposAudio.value[Number(id)];
  return tiempo?.total ? tiempo.actual / tiempo.total : 0;
};

const formatoTiempoAudio = (segundos: number) => {
  if (!Number.isFinite(segundos) || segundos < 0) return "0:00";
  const total = Math.floor(segundos);
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, "0")}`;
};

const tiempoAudio = (id: number) => formatoTiempoAudio(tiemposAudio.value[Number(id)]?.actual || 0);
const duracionAudio = (id: number) => formatoTiempoAudio(tiemposAudio.value[Number(id)]?.total || 0);

const buscarAudio = (evento: MouseEvent, id: number) => {
  const audio = reproductoresAudio.get(Number(id));
  const onda = evento.currentTarget as HTMLElement;
  if (!audio || !audio.duration || !onda) return;
  const rect = onda.getBoundingClientRect();
  audio.currentTime = Math.max(0, Math.min(1, (evento.clientX - rect.left) / rect.width)) * audio.duration;
  actualizarProgresoAudio(id);
};

const prepararAdjunto = async (mensaje: Mensaje): Promise<Mensaje> => {
  const archivoUrl = mensaje.archivo_url;
  if (!archivoUrl || !["imagen", "audio"].includes(mensaje.mensaje_tipo)) return mensaje;
  try {
    let blob: Blob | null = null;
    try {
      blob = await leerAdjuntoLocal(Number(mensaje.id));
    } catch (error) {
      console.warn("No se pudo leer el almacenamiento local; se descargará el archivo:", error);
    }
    if (!blob) {
      const datos = new URLSearchParams({
        token: localStorage.getItem("token") || "",
        mensaje_id: String(mensaje.id),
      });
      const respuesta = await fetch(`${apiServidor}/controladores/mensajes_controlador.php?task=obtenerArchivo`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
        cache: "no-store",
      });
      if (!respuesta.ok) throw new Error("No se pudo descargar el archivo del chat.");
      blob = await respuesta.blob();
      try {
        await guardarAdjuntoLocal(Number(mensaje.id), blob, mensaje.archivo_nombre || "archivo");
      } catch (error) {
        console.warn("El archivo se verá, pero no pudo guardarse localmente:", error);
      }
    }
    if (!blob) throw new Error("No se recibió el archivo.");
    const previa = urlsAudioImagen.get(Number(mensaje.id));
    if (previa) URL.revokeObjectURL(previa);
    const localUrl = URL.createObjectURL(blob);
    urlsAudioImagen.set(Number(mensaje.id), localUrl);
    return { ...mensaje, archivo_local_url: localUrl };
  } catch (error) {
    console.warn("No se pudo cachear localmente un adjunto:", error);
    return { ...mensaje, archivo_local_url: undefined };
  }
};

const seleccionarAdjunto = async (evento: Event) => {
  const input = evento.target as HTMLInputElement;
  const archivo = input.files?.[0];
  if (!archivo) return;
  const extension = archivo.name.split(".").pop()?.toLowerCase() || "";
  const extensionesImagen = ["jpg", "jpeg", "png", "webp", "gif", "avif"];
  const extensionesAudio = ["mp3", "m4a", "aac", "ogg", "oga", "webm", "wav", "3gp"];
  const esImagen = archivo.type.startsWith("image/") || (!archivo.type && extensionesImagen.includes(extension));
  const esAudio = archivo.type.startsWith("audio/") || (!archivo.type && extensionesAudio.includes(extension));
  if ((!esImagen && !esAudio) || archivo.size > (esImagen ? 10 : 20) * 1024 * 1024) {
    const alerta = await alertController.create({
      mode: "ios",
      header: "Archivo no válido",
      message: esImagen || esAudio
        ? `El archivo supera el límite de ${esImagen ? "10" : "20"} MB.`
        : "Solo puedes adjuntar imágenes o audios.",
      buttons: ["OK"],
    });
    await alerta.present();
    input.value = "";
    return;
  }
  nuevoMensaje.value = "";
  archivoPendiente.value = archivo;
};

const alternarGrabacionAudio = async () => {
  if (grabadorAudio.value?.state === "recording") {
    grabadorAudio.value.stop();
    return;
  }
  try {
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === "undefined") {
      throw new Error("Este dispositivo no admite grabación de audio desde la app.");
    }
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const mime = ["audio/webm;codecs=opus", "audio/ogg;codecs=opus", "audio/mp4"]
      .find((tipo) => MediaRecorder.isTypeSupported(tipo));
    const grabador = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
    nuevoMensaje.value = "";
    fragmentosAudio = [];
    grabador.ondataavailable = (evento) => { if (evento.data.size) fragmentosAudio.push(evento.data); };
    grabador.onstop = async () => {
      const tipo = grabador.mimeType || "audio/webm";
      const extension = tipo.includes("ogg") ? "ogg" : tipo.includes("mp4") ? "m4a" : "webm";
      const partes = fragmentosAudio.filter((parte) => parte.size > 0);
      const notaDeVoz = new File(partes, `audio-${Date.now()}.${extension}`, { type: tipo });
      grabadorAudio.value = null;
      grabandoAudio.value = false;
      if (intervaloGrabacion) clearInterval(intervaloGrabacion);
      intervaloGrabacion = null;
      stream.getTracks().forEach((pista) => pista.stop());
      if (notaDeVoz.size < 512) {
        const alerta = await alertController.create({
          mode: "ios",
          header: "No se grabó el audio",
          message: "Mantén la grabación un poco más y vuelve a intentarlo.",
          buttons: ["OK"],
        });
        await alerta.present();
        return;
      }
      archivoPendiente.value = notaDeVoz;
      await nextTick();
      await enviarArchivoChat();
    };
    grabadorAudio.value = grabador;
    grabandoAudio.value = true;
    grabacionSegundos.value = 0;
    // Emit chunks during recording so WebViews reliably include the final audio data.
    grabador.start(250);
    intervaloGrabacion = setInterval(() => { grabacionSegundos.value += 1; }, 1000);
  } catch (error) {
    const mensaje = error instanceof Error ? error.message : "No se pudo iniciar la grabación.";
    const alerta = await alertController.create({ mode: "ios", header: "Audio no disponible", message: mensaje, buttons: ["OK"] });
    await alerta.present();
  }
};

const enviarArchivoChat = async () => {
  const archivo = archivoPendiente.value;
  const token = localStorage.getItem("token") || "";
  const conversacionId = String(route.params.id || "");
  if (!archivo || !token || !conversacionId || enviandoArchivo.value) return;
  enviandoArchivo.value = true;
  try {
    const datos = new FormData();
    datos.append("token", token);
    datos.append("conversacion_id", conversacionId);
    datos.append("archivo", archivo);
    const respuesta = await fetch(
      `${apiServidor}/controladores/mensajes_controlador.php?task=enviarArchivo`,
      { method: "POST", body: datos, cache: "no-store" },
    );
    const resultado = await respuesta.json().catch(() => null);
    if (!respuesta.ok || !resultado?.ok) throw new Error(resultado?.error || `El servidor rechazó el archivo (HTTP ${respuesta.status}). Revisa el tamaño permitido y la conexión con la API.`);
    archivoPendiente.value = null;
    if (entradaAdjunto.value) entradaAdjunto.value.value = "";
    while (cargandoMensajes) await new Promise((resolve) => setTimeout(resolve, 50));
    await cargarMensajes(true);
  } catch (error) {
    const alerta = await alertController.create({ mode: "ios", header: "No se pudo enviar", message: error instanceof Error ? error.message : "Inténtalo de nuevo.", buttons: ["OK"] });
    await alerta.present();
  } finally {
    enviandoArchivo.value = false;
  }
};

const eliminarMensajeParaTodos = async (mensaje: Mensaje) => {
  const confirmar = await alertController.create({
    mode: "ios",
    header: "Eliminar para todos",
    message: "Se quitará el mensaje y su archivo del chat de ambos usuarios.",
    buttons: [
      { text: "Cancelar", role: "cancel" },
      { text: "Eliminar", role: "destructive" },
    ],
  });
  await confirmar.present();
  const { role } = await confirmar.onDidDismiss();
  if (role !== "destructive") return;
  try {
    const datos = new URLSearchParams({
      token: localStorage.getItem("token") || "",
      mensaje_id: String(mensaje.id),
    });
    const respuesta = await fetch(
      `${apiServidor}/controladores/mensajes_controlador.php?task=eliminarMensajeParaTodos`,
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datos.toString(), cache: "no-store" },
    );
    const resultado = await respuesta.json();
    if (!respuesta.ok || !resultado.ok) throw new Error(resultado.error || "No se pudo eliminar el mensaje.");
    await procesarEliminados([mensaje.id]);
  } catch (error) {
    const alerta = await alertController.create({ mode: "ios", header: "No se pudo eliminar", message: error instanceof Error ? error.message : "Inténtalo de nuevo.", buttons: ["OK"] });
    await alerta.present();
  }
};

const procesarEliminados = async (ids: number[]) => {
  const unicos = [...new Set(ids.map(Number))];
  for (const id of unicos) {
    const url = urlsAudioImagen.get(id);
    if (url) URL.revokeObjectURL(url);
    urlsAudioImagen.delete(id);
    reproductoresAudio.get(id)?.pause();
    reproductoresAudio.delete(id);
    const tiemposRestantes = { ...tiemposAudio.value };
    delete tiemposRestantes[id];
    tiemposAudio.value = tiemposRestantes;
    try { await quitarAdjuntoLocal(id); } catch (error) { console.warn("No se pudo quitar la copia local del adjunto:", error); }
  }
  const eliminados = new Set(unicos);
  mensajes.value = mensajes.value.map((mensaje) => eliminados.has(Number(mensaje.id))
    ? { ...mensaje, mensaje: "Este mensaje fue eliminado", mensaje_tipo: "eliminado", archivo_url: null, archivo_local_url: null }
    : mensaje);
};

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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/mensajes_controlador.php?task=obtenerMensajes",
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
    const idsEliminados = Array.isArray(resultado.eliminados)
      ? resultado.eliminados.map(Number)
      : [];
    const estados: { id: number; estado_mensaje: Mensaje["estado_mensaje"] }[] =
      resultado.estados || [];

    // Se mide ANTES de actualizar para saber si ya estabas abajo
    const estabaAbajo = await estaCercaDelFinal();

    if (nuevos.length > 0) {
      const adjuntosPreparados = await Promise.all(nuevos.map(prepararAdjunto));
      mensajes.value = [...mensajes.value, ...adjuntosPreparados];
    }

    if (idsEliminados.length > 0) await procesarEliminados(idsEliminados);

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
  for (const url of urlsAudioImagen.values()) URL.revokeObjectURL(url);
  urlsAudioImagen.clear();
  archivoPendiente.value = null;
  contacto.value = {
    id: 0,
    nombre: "Chat",
    avatar: "",
    descripcion: "",
    telefono: "",
    ultimaConexion: null,
    enLinea: false,
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/mensajes_controlador.php?task=enviarMensaje",
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

  if (grabadorAudio.value?.state === "recording") grabadorAudio.value.stop();
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
  if (intervaloGrabacion) {
    clearInterval(intervaloGrabacion);
    intervaloGrabacion = null;
  }
  if (grabadorAudio.value?.state === "recording") grabadorAudio.value.stop();
  for (const audio of reproductoresAudio.values()) audio.pause();
  reproductoresAudio.clear();
  for (const url of urlsAudioImagen.values()) URL.revokeObjectURL(url);
  urlsAudioImagen.clear();
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
  background: var(--app-chat-wallpaper,
      radial-gradient(circle at 20% 20%,
        rgba(16, 185, 129, 0.07),
        transparent 30%),
      radial-gradient(circle at 80% 80%,
        rgba(6, 182, 212, 0.07),
        transparent 30%),
      #090d16);
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

.burbuja-media {
  flex-direction: column;
}

.media-imagen {
  display: block;
  width: min(64vw, 320px);
  max-height: 360px;
  object-fit: contain;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.12);
}

.media-audio {
  display: none;
}

.audio-elemento-oculto {
  position: fixed;
  left: -10000px;
  bottom: 0;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.reproductor-audio {
  display: flex;
  align-items: center;
  gap: 8px;
  width: min(68vw, 320px);
  min-width: 225px;
  padding: 2px 0;
}

.audio-play {
  flex: 0 0 42px;
  width: 42px;
  height: 42px;
  margin: 0;
  --color: #ffffff;
  --background: rgba(255, 255, 255, 0.16);
  --border-radius: 50%;
}

.audio-play ion-icon {
  font-size: 24px;
}

.audio-detalles {
  flex: 1;
  min-width: 0;
}

.onda-audio {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2px;
  height: 27px;
  cursor: pointer;
}

.onda-audio i {
  flex: 1;
  min-width: 2px;
  max-width: 4px;
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.48);
}

.onda-audio i.activa {
  background: #ffffff;
}

.audio-tiempo {
  display: flex;
  justify-content: space-between;
  color: rgba(255, 255, 255, 0.8);
  font-size: 10px;
  line-height: 13px;
}

.estado-grabacion {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #fecdd3;
  font-size: 12px;
  white-space: nowrap;
}

.estado-grabacion i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #fb7185;
  animation: pulso-grabacion 1s infinite;
}

@keyframes pulso-grabacion {
  50% {
    opacity: 0.35;
  }
}

.eliminar-mensaje {
  align-self: flex-end;
  width: 26px;
  height: 25px;
  margin: -3px -5px -4px 0;
  --color: rgba(255, 255, 255, 0.7);
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
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: rgba(18, 24, 38, 0.92);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.entrada-adjunto {
  display: none;
}

.adjunto-pendiente {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  padding: 5px 10px;
  color: #cbd5e1;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  font-size: 12px;
}

.adjunto-pendiente span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.boton-adjunto {
  flex-shrink: 0;
  margin: 0;
  --color: #94a3b8;
}

.boton-adjunto.grabando-audio {
  --color: #fb7185;
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
