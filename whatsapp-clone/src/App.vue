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
  </ion-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  IonApp,
  IonTabs,
  IonRouterOutlet,
  IonAlert,
} from "@ionic/vue";
import { useRoute } from "vue-router";
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
  }, 2000);
});

onUnmounted(() => {
  if (intervaloLlamadas) {
    clearInterval(intervaloLlamadas);
    intervaloLlamadas = null;
  }
});
</script>
