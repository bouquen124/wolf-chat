<template>
  <ion-page class="grupo-chat-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/grupos" />
        </ion-buttons>
        <div class="header-contacto">
          <div class="avatar-ring-header">
            <ion-avatar class="avatar-header">
              <img :src="grupo.foto || 'https://i.pravatar.cc/150?img=11'" />
            </ion-avatar>
          </div>
          <ion-title>{{ grupo.nombre }}</ion-title>
        </div>
      </ion-toolbar>
    </ion-header>

    <ion-content ref="contenido" class="chat-content">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="mensajes">
        <div
          v-for="mensaje in mensajes"
          :key="mensaje.id"
          :class="[
            'mensaje',
            esMiMensaje(mensaje.remitente_id) ? 'mensaje-yo' : 'mensaje-otro',
          ]"
        >
          <div class="burbuja">
            <strong v-if="!esMiMensaje(mensaje.remitente_id)">
              {{ mensaje.nombre }} {{ mensaje.apellido || "" }}
            </strong>

            <span class="texto">{{ mensaje.mensaje }}</span>

            <small>
              {{ formatearHora(mensaje.fecha_envio) }}
              <span
                v-if="esMiMensaje(mensaje.remitente_id)"
                class="estado-mensaje"
                :class="{ 'mensaje-leido': mensaje.estado_mensaje === 'leido' }"
              >
                {{
                  mensaje.estado_mensaje === "leido" ||
                  mensaje.estado_mensaje === "entregado"
                    ? "✓✓"
                    : "✓"
                }}
              </span>
            </small>
          </div>
        </div>
      </div>
    </ion-content>

    <ion-footer class="glass-footer">
      <div class="barra-mensaje">
        <ion-input
          v-model="nuevoMensaje"
          placeholder="Escribe un mensaje..."
          @keyup.enter="enviarMensaje"
        />

        <ion-button
          class="send-button"
          :disabled="enviando"
          @click="enviarMensaje"
        >
          <ion-icon :icon="sendOutline" />
        </ion-button>
      </div>
    </ion-footer>
  </ion-page>
</template>

<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref } from "vue";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonAvatar,
  IonContent,
  IonFooter,
  IonInput,
  IonButton,
  IonIcon,
  onIonViewWillEnter,
  onIonViewWillLeave,
} from "@ionic/vue";
import { sendOutline } from "ionicons/icons";
import { useRoute } from "vue-router";

interface Grupo {
  conversacion_id: number;
  nombre: string;
  foto: string | null;
}

interface Mensaje {
  id: number;
  conversacion_id: number;
  remitente_id: number | string;
  mensaje: string;
  mensaje_tipo: string;
  fecha_envio: string;
  nombre: string;
  apellido: string | null;
  estado_mensaje: "enviado" | "entregado" | "leido" | null;
}

const route = useRoute();

const token = localStorage.getItem("token") || "";

const obtenerUsuarioId = () => {
  const directo = Number(localStorage.getItem("usuario_id") || 0);

  if (directo) {
    return directo;
  }

  try {
    const usuario = JSON.parse(localStorage.getItem("usuario") || "{}");
    return Number(usuario.id || 0);
  } catch {
    return 0;
  }
};

const usuarioId = obtenerUsuarioId();
const conversacionId = Number(route.params.id);

const grupo = ref<Grupo>({
  conversacion_id: conversacionId,
  nombre: "Grupo",
  foto: null,
});

const nuevoMensaje = ref("");
const mensajes = ref<Mensaje[]>([]);
const enviando = ref(false);

// Referencia al ion-content para controlar el scroll
const contenido = ref<InstanceType<typeof IonContent> | null>(null);

let intervaloMensajes: ReturnType<typeof setInterval> | null = null;
let primeraCargaMensajes = true;
let cargandoMensajes = false;

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

/* ===================== HELPERS ===================== */

const esMiMensaje = (remitenteId: number | string) => {
  return Number(remitenteId) === usuarioId;
};

/* ===================== API ===================== */

const obtenerGrupo = async () => {
  try {
    const formData = new FormData();

    formData.append("token", token);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/conversaciones_controlador.php?task=obtenerGrupos",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (datos.ok) {
      const grupoEncontrado = (datos.grupos || []).find(
        (g: Grupo) => Number(g.conversacion_id) === conversacionId,
      );

      if (grupoEncontrado) {
        grupo.value = {
          conversacion_id: grupoEncontrado.conversacion_id,
          nombre: grupoEncontrado.nombre,
          foto: grupoEncontrado.foto,
        };
      }
    }
  } catch (error) {
    console.error("Error al obtener grupo:", error);
  }
};

const obtenerMensajes = async (forzarScroll = false) => {
  // Evita que se encimen peticiones si el servidor tarda más de 2 s
  if (cargandoMensajes) {
    return;
  }

  cargandoMensajes = true;

  try {
    const formData = new FormData();

    formData.append("token", token);
    formData.append("conversacion_id", String(conversacionId));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/mensajes_controlador.php?task=obtenerMensajes",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (!datos.ok) {
      return;
    }

    const nuevosMensajes: Mensaje[] = datos.mensajes || [];

    const ultimoIdAnterior =
      mensajes.value.length > 0
        ? mensajes.value[mensajes.value.length - 1].id
        : null;

    const ultimoIdNuevo =
      nuevosMensajes.length > 0
        ? nuevosMensajes[nuevosMensajes.length - 1].id
        : null;

    const hayMensajeNuevo = ultimoIdNuevo !== ultimoIdAnterior;

    // Se mide ANTES de actualizar para saber si ya estabas abajo
    const estabaAbajo = await estaCercaDelFinal();

    mensajes.value = [...nuevosMensajes];

    if (primeraCargaMensajes) {
      primeraCargaMensajes = false;
      await scrollAlFinal(0);
      return;
    }

    if (forzarScroll || (hayMensajeNuevo && estabaAbajo)) {
      await scrollAlFinal(200);
    }
  } catch (error) {
    console.error("Error al obtener mensajes:", error);
  } finally {
    cargandoMensajes = false;
  }
};

const enviarMensaje = async () => {
  const texto = nuevoMensaje.value.trim();

  if (!texto || enviando.value) {
    return;
  }

  enviando.value = true;

  try {
    const formData = new FormData();

    formData.append("token", token);
    formData.append("conversacion_id", String(conversacionId));
    formData.append("mensaje", texto);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/mensajes_controlador.php?task=enviarMensaje",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (datos.ok) {
      nuevoMensaje.value = "";

      // Si el polling está en medio de una petición, espera a que termine
      while (cargandoMensajes) {
        await new Promise((resolve) => setTimeout(resolve, 50));
      }

      // Al enviar, siempre baja al último mensaje
      await obtenerMensajes(true);

      // Avisa a la lista de grupos para que actualice el último mensaje
      window.dispatchEvent(
        new CustomEvent("grupo-mensaje-enviado", {
          detail: {
            conversacion_id: conversacionId,
          },
        }),
      );
    } else {
      console.error(datos.error || "No se pudo enviar el mensaje");
    }
  } catch (error) {
    console.error("Error al enviar mensaje:", error);
  } finally {
    enviando.value = false;
  }
};

/* ===================== POLLING ===================== */

const iniciarPolling = () => {
  if (intervaloMensajes) {
    return;
  }

  intervaloMensajes = setInterval(() => {
    obtenerMensajes();
  }, 2000);
};

const detenerPolling = () => {
  if (intervaloMensajes) {
    clearInterval(intervaloMensajes);
    intervaloMensajes = null;
  }
};

const cargarGrupo = async () => {
  primeraCargaMensajes = true;

  await obtenerGrupo();
  await obtenerMensajes();

  iniciarPolling();
};

const formatearHora = (fecha: string) => {
  if (!fecha) {
    return "";
  }

  const fechaMensaje = new Date(fecha.replace(" ", "T"));

  return fechaMensaje.toLocaleTimeString("es-MX", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
};

/* ===================== CICLO DE VIDA ===================== */

onMounted(async () => {
  await cargarGrupo();
});

onIonViewWillEnter(async () => {
  await cargarGrupo();
});

onIonViewWillLeave(() => {
  detenerPolling();
});

onUnmounted(() => {
  detenerPolling();
});
</script>

<style scoped>
.grupo-chat-page {
  --ion-background-color: #090d16;
  background: #090d16;
}

.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}

.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.78);
  --border-width: 0;
}

.glass-header ion-buttons ion-back-button {
  --color: #ffffff;
}

.header-contacto {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.header-contacto ion-title {
  padding-left: 10px;
  font-size: 18px;
  font-weight: 650;
  color: #ffffff;
}

.avatar-ring-header {
  width: 40px;
  height: 40px;
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.22);
}

.avatar-header {
  width: 36px;
  height: 36px;
  border: 2px solid #090d16;
}

.avatar-header img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.chat-content {
  --background: #090d16;
  position: relative;
}

.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.14;
  pointer-events: none;
  z-index: 0;
}

.glow-emerald {
  top: 40px;
  left: -140px;
  background: #10b981;
}

.glow-cyan {
  bottom: 80px;
  right: -140px;
  background: #06b6d4;
}

.mensajes {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px 14px 20px;
  min-height: 100%;
}

.mensaje {
  display: flex;
}

.mensaje-yo {
  justify-content: flex-end;
}

.mensaje-otro {
  justify-content: flex-start;
}

.burbuja {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 80%;
  padding: 10px 12px;
  border-radius: 18px;
  font-size: 15px;
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 6px 22px rgba(0, 0, 0, 0.16);
}

.mensaje-yo .burbuja {
  background: linear-gradient(
    135deg,
    rgba(16, 185, 129, 0.28),
    rgba(6, 182, 212, 0.22)
  );
  border-color: rgba(16, 185, 129, 0.18);
  border-bottom-right-radius: 5px;
}

.mensaje-otro .burbuja {
  background: rgba(255, 255, 255, 0.055);
  border-bottom-left-radius: 5px;
}

.burbuja strong {
  font-size: 12px;
  color: #10b981;
  margin-bottom: 4px;
}

.burbuja .texto {
  line-height: 1.4;
  color: #f2f5f8;
  overflow-wrap: anywhere;
}

.burbuja small {
  align-self: flex-end;
  font-size: 10px;
  color: #7c8797;
  margin-top: 5px;
}

.estado-mensaje {
  margin-left: 3px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: -1px;
  color: #7c8797;
}

.estado-mensaje.mensaje-leido {
  color: #06b6d4;
}

.glass-footer {
  background: rgba(9, 13, 22, 0.82);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

.glass-footer::before {
  display: none;
}

.barra-mensaje {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: transparent;
}

.barra-mensaje ion-input {
  --background: rgba(255, 255, 255, 0.055);
  --color: #ffffff;
  --placeholder-color: #7d8798;
  --padding-start: 16px;
  --padding-end: 16px;
  --padding-top: 11px;
  --padding-bottom: 11px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 18px;
}

.send-button {
  --background: linear-gradient(135deg, #10b981, #06b6d4);
  --background-activated: linear-gradient(135deg, #059669, #0891b2);
  --color: #ffffff;
  --border-radius: 16px;
  --box-shadow: 0 0 18px rgba(16, 185, 129, 0.24);
  width: 46px;
  height: 46px;
  margin: 0;
}

.send-button ion-icon {
  font-size: 21px;
}
</style>