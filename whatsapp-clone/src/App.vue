<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\App.vue -->

<template>
  <ion-app>
    <ion-tabs>
      <ion-router-outlet />
      <!-- Solo se muestra la barra inferior si NO estamos en Login ni Registro -->
      <TabBar v-if="mostrarTabBar" />
    </ion-tabs>

    <ion-alert
      :is-open="llamadaEntrante !== null && !enChat"
      header="Llamada entrante"
      :message="mensajeLlamadaEntrante()"
      :buttons="botonesLlamada"
      @didDismiss="cerrarLlamadaEntrante"
    />

    <Transition name="notificacion-pop">
      <div v-if="avisoActual" class="notificacion-capa" aria-live="polite">
        <article :class="['notificacion-tarjeta', `notificacion-${avisoActual.tipo}`]">
          <div class="notificacion-avatar">
            <img v-if="avisoActual.foto" :src="avisoActual.foto" alt="Foto del remitente" />
            <ion-icon v-else :icon="iconoAviso" aria-hidden="true" />
          </div>

          <div class="notificacion-detalle">
            <div class="notificacion-meta">
              <span>{{ etiquetaAviso }}</span>
              <button class="notificacion-cerrar" aria-label="Cerrar aviso" @click="mostrarSiguienteAviso">×</button>
            </div>
            <h3>{{ tituloAviso }}</h3>
            <p>{{ mensajeAviso }}</p>
            <button class="notificacion-abrir" @click="abrirAviso">
              {{ avisoActual.tipo === "estado" ? "Ver historia" : "Abrir conversación" }}
              <ion-icon :icon="flechaAviso" aria-hidden="true" />
            </button>
          </div>
        </article>
      </div>
    </Transition>
  </ion-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  IonApp,
  IonTabs,
  IonRouterOutlet,
  IonAlert,
  IonIcon,
} from "@ionic/vue";
import { useRoute, useRouter } from "vue-router";
import {
  chatbubbleEllipsesOutline,
  peopleOutline,
  imagesOutline,
  arrowForwardOutline,
} from "ionicons/icons";
import TabBar from "./components/TabBar.vue";
import {
  aplicarPreferenciasApp,
  guardarFondoEnCuenta,
  guardarPreferenciasApp,
  guardarTemaEnCuenta,
  leerPreferenciasApp,
  sincronizarPreferenciasCuenta,
} from "./utils/appPreferences";

const route = useRoute();
const router = useRouter();

const enChat = ref(false);

// Ocultar TabBar en rutas públicas/autenticación
const mostrarTabBar = computed(() => {
  const rutaActual = String(route.path || "").toLowerCase();
  const nombreRuta = String(route.name || "").toLowerCase();

  // Si la ruta contiene login o registro, no muestra la barra inferior
  const esRutaPublica =
    rutaActual.includes("login") ||
    rutaActual.includes("registro") ||
    nombreRuta.includes("login") ||
    nombreRuta.includes("registro");

  return !esRutaPublica;
});

const revisarRuta = () => {
  enChat.value = route.name === "Chat";
};

interface LlamadaEntrante {
  id: number;
  conversacion_id: number;
  llamante_id: number;
  receptor_id: number;
  tipo: string;
  estado: string;
  nombre: string;
  apellido: string;
  foto: string | null;
}

const llamadaEntrante = ref<LlamadaEntrante | null>(null);

let intervaloLlamadas: ReturnType<typeof setInterval> | null = null;
let intervaloPresencia: ReturnType<typeof setInterval> | null = null;
let solicitudNotificacionEnCurso = false;
interface NotificacionApp {
  id: number;
  tipo: "privado" | "grupo" | "estado";
  titulo: string;
  contenido: string | null;
  referencia_id: number | null;
  remitente_id: number | null;
  foto: string | null;
}
const avisoActual = ref<NotificacionApp | null>(null);
const colaAvisos = ref<NotificacionApp[]>([]);
const tituloAviso = computed(() => avisoActual.value?.titulo || "");
const mensajeAviso = computed(() => avisoActual.value?.contenido || "");
const iconoAviso = computed(() => {
  if (avisoActual.value?.tipo === "grupo") return peopleOutline;
  if (avisoActual.value?.tipo === "estado") return imagesOutline;
  return chatbubbleEllipsesOutline;
});
const etiquetaAviso = computed(() => {
  if (avisoActual.value?.tipo === "grupo") return "MENSAJE DE GRUPO";
  if (avisoActual.value?.tipo === "estado") return "NUEVA HISTORIA";
  return "MENSAJE PRIVADO";
});
const flechaAviso = arrowForwardOutline;
let temporizadorAviso: ReturnType<typeof setTimeout> | null = null;

const abrirAviso = () => {
  const aviso = avisoActual.value;
  if (!aviso) return;
  abrirDestinoAviso(aviso);
  mostrarSiguienteAviso();
};

const abrirDestinoAviso = (aviso: NotificacionApp) => {
  if (aviso.tipo === "estado" && aviso.referencia_id) {
    const destino = `/historia/${aviso.referencia_id}`;
    if (route.path !== destino) void router.push(destino);
  } else if (aviso.referencia_id) {
    const destino = aviso.tipo === "grupo"
      ? `/grupo/${aviso.referencia_id}`
      : `/chat/${aviso.referencia_id}`;
    if (route.path !== destino) void router.push(destino);
  }
};

const mostrarSiguienteAviso = () => {
  if (temporizadorAviso) {
    clearTimeout(temporizadorAviso);
    temporizadorAviso = null;
  }
  avisoActual.value = colaAvisos.value.shift() || null;
  if (avisoActual.value) {
    temporizadorAviso = setTimeout(mostrarSiguienteAviso, 7000);
  }
};

const recibirNotificacion = (notificacion: NotificacionApp) => {
  if (avisoActual.value) colaAvisos.value.push(notificacion);
  else {
    avisoActual.value = notificacion;
    temporizadorAviso = setTimeout(mostrarSiguienteAviso, 7000);
  }

  if (document.visibilityState === "hidden" && "Notification" in window && Notification.permission === "granted") {
    const delSistema = new Notification(notificacion.titulo, {
      body: notificacion.contenido || "",
      icon: notificacion.foto || undefined,
    });
    delSistema.onclick = () => {
      window.focus();
      abrirDestinoAviso(notificacion);
      delSistema.close();
    };
  }
};

const revisarNotificacionPendiente = async () => {
  if (solicitudNotificacionEnCurso || !localStorage.getItem("token")) return;
  solicitudNotificacionEnCurso = true;
  try {
    const datos = new URLSearchParams({ token: localStorage.getItem("token") || "" });
    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/notificaciones_controlador.php?task=obtenerPendiente",
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datos.toString(), cache: "no-store" },
    );
    const resultado = await respuesta.json();
    if (resultado.ok && resultado.notificacion) recibirNotificacion(resultado.notificacion);
  } catch (error) {
    console.error("No se pudo revisar la bandeja de notificaciones:", error);
  } finally {
    solicitudNotificacionEnCurso = false;
  }
};

const registrarActividad = async () => {
  const token = localStorage.getItem("token");
  if (!token || document.visibilityState === "hidden") return;
  try {
    const datos = new URLSearchParams({ token });
    await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=registrarActividad",
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datos.toString(), cache: "no-store" },
    );
  } catch (error) {
    console.error("No se pudo actualizar la última conexión:", error);
  }
};

const revisarLlamadaEntrante = async () => {
  try {
    if (enChat.value) {
      llamadaEntrante.value = null;
      return;
    }

    const token = localStorage.getItem("token");

    if (!token) {
      llamadaEntrante.value = null;
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
    } else {
      llamadaEntrante.value = null;
    }
  } catch (error) {
    console.error("Error al revisar llamada entrante:", error);
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
      llamadaEntrante.value = null;
      alert(resultado.error);
      return;
    }

    llamadaEntrante.value = null;

    alert("Llamada aceptada");
  } catch (error) {
    console.error("Error al aceptar llamada:", error);
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
      llamadaEntrante.value = null;
      alert(resultado.error);
      return;
    }

    llamadaEntrante.value = null;
  } catch (error) {
    console.error("Error al rechazar llamada:", error);
    alert("Error al rechazar la llamada");
  }
};

const cerrarLlamadaEntrante = () => {
  llamadaEntrante.value = null;
};

const mensajeLlamadaEntrante = () => {
  if (!llamadaEntrante.value) {
    return "";
  }

  return `${llamadaEntrante.value.nombre} ${llamadaEntrante.value.apellido} te está llamando`;
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

const sincronizarPreferencias = async () => {
  const token = localStorage.getItem("token");
  if (!token) return;

  try {
    const remoto = await sincronizarPreferenciasCuenta(token);
    if (remoto.configurada) {
      guardarPreferenciasApp(remoto.preferencias);
      return;
    }

    // Migra al perfil las preferencias locales que existían antes de sincronizar.
    const local = leerPreferenciasApp();
    let guardadas = await guardarTemaEnCuenta(token, local.themeId);
    if (local.wallpaperUrl?.startsWith("data:image/")) {
      const fondo = await fetch(local.wallpaperUrl).then((respuesta) => respuesta.blob());
      guardadas = await guardarFondoEnCuenta(token, fondo);
    }
    guardarPreferenciasApp(guardadas);
  } catch (error) {
    console.error("No se pudieron sincronizar las preferencias de la cuenta:", error);
  }
};

let tokenPreferenciasSincronizadas = "";
watch(
  () => route.path,
  () => {
    const token = localStorage.getItem("token") || "";
    if (!token) {
      tokenPreferenciasSincronizadas = "";
      return;
    }
    if (token !== tokenPreferenciasSincronizadas) {
      tokenPreferenciasSincronizadas = token;
      void sincronizarPreferencias();
    }
  },
  { immediate: true },
);

onMounted(() => {
  aplicarPreferenciasApp();
  revisarRuta();
  revisarLlamadaEntrante();

  intervaloLlamadas = setInterval(() => {
    revisarRuta();
    revisarLlamadaEntrante();
    revisarNotificacionPendiente();
  }, 2000);
  void registrarActividad();
  intervaloPresencia = setInterval(registrarActividad, 30000);
});

onUnmounted(() => {
  if (intervaloLlamadas) {
    clearInterval(intervaloLlamadas);
    intervaloLlamadas = null;
  }
  if (intervaloPresencia) {
    clearInterval(intervaloPresencia);
    intervaloPresencia = null;
  }
  if (temporizadorAviso) {
    clearTimeout(temporizadorAviso);
    temporizadorAviso = null;
  }
});
</script>

<style>
.notificacion-capa {
  position: fixed;
  z-index: 10000;
  top: calc(env(safe-area-inset-top) + 12px);
  left: 12px;
  right: 12px;
  display: flex;
  justify-content: center;
  pointer-events: none;
}

.notificacion-tarjeta {
  --notificacion-acento: #10b981;
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 13px;
  width: min(100%, 440px);
  padding: 14px;
  overflow: hidden;
  pointer-events: auto;
  color: #f8fafc;
  background: linear-gradient(135deg, rgba(18, 27, 42, 0.97), rgba(9, 14, 25, 0.97));
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-left: 4px solid var(--notificacion-acento);
  border-radius: 20px;
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.46), 0 0 28px color-mix(in srgb, var(--notificacion-acento) 18%, transparent);
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
}

.notificacion-grupo { --notificacion-acento: #a78bfa; }
.notificacion-estado { --notificacion-acento: #fbbf24; }

.notificacion-avatar {
  display: grid;
  flex: 0 0 52px;
  width: 52px;
  height: 52px;
  place-items: center;
  overflow: hidden;
  color: var(--notificacion-acento);
  background: color-mix(in srgb, var(--notificacion-acento) 15%, #111827);
  border: 2px solid color-mix(in srgb, var(--notificacion-acento) 65%, transparent);
  border-radius: 50%;
}

.notificacion-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.notificacion-avatar ion-icon { font-size: 24px; }
.notificacion-detalle { min-width: 0; flex: 1; }

.notificacion-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--notificacion-acento);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.9px;
}

.notificacion-cerrar {
  width: 26px;
  height: 26px;
  padding: 0;
  color: #cbd5e1;
  background: rgba(255, 255, 255, 0.08);
  border: 0;
  border-radius: 50%;
  font-size: 20px;
  line-height: 1;
}

.notificacion-detalle h3 {
  margin: 2px 0 3px;
  overflow: hidden;
  color: #fff;
  font-size: 15px;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notificacion-detalle p {
  display: -webkit-box;
  margin: 0;
  overflow: hidden;
  color: rgba(226, 232, 240, 0.88);
  font-size: 13px;
  line-height: 1.4;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.notificacion-abrir {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  padding: 0;
  color: var(--notificacion-acento);
  background: transparent;
  border: 0;
  font-size: 12px;
  font-weight: 750;
}

.notificacion-abrir ion-icon { font-size: 15px; }

.notificacion-pop-enter-active,
.notificacion-pop-leave-active { transition: opacity 180ms ease, transform 180ms ease; }
.notificacion-pop-enter-from,
.notificacion-pop-leave-to { opacity: 0; transform: translateY(-12px) scale(0.98); }
</style>
