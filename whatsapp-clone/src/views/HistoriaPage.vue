<template>
  <ion-page class="historia-page">
    ```
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button class="boton-cerrar" @click="cerrarHistorias">
            <ion-icon :icon="closeOutline" />
          </ion-button>
        </ion-buttons>

        <ion-avatar class="avatar-header">
          <img :src="historia.avatar" />
        </ion-avatar>

        <ion-title>
          {{ historia.nombre }}
        </ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="historia-content">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="historia-container">
        <div class="progreso-historias">
          <div v-for="item in historias" :key="item.id" class="progreso">
            <div
              class="progreso-activo"
              :class="{
                completado: indiceActual > historias.indexOf(item),
              }"
              :style="item.id === historia.id ? { width: progreso + '%' } : {}"
            ></div>
          </div>
        </div>

        <div class="zona-historia" @click="alternarPausa">
          <div v-if="historia.tipo === 'texto'" class="texto-wrapper">
            <div class="historia-texto">
              {{ historia.contenido }}
            </div>
          </div>

          <div v-else-if="historia.tipo === 'imagen'" class="imagen-wrapper">
            <img
              :src="historia.archivo"
              :alt="historia.nombre"
              class="historia-imagen"
            />
          </div>

          <div v-else-if="historia.tipo === 'video'" class="imagen-wrapper">
            <video
              :src="historia.archivo"
              class="historia-video"
              autoplay
              playsinline
              controls
            ></video>
          </div>
        </div>

        <button class="boton-anterior" @click.stop="historiaAnterior">
          <ion-icon :icon="chevronBackOutline" />
        </button>

        <button class="boton-siguiente" @click.stop="siguienteHistoria">
          <ion-icon :icon="chevronForwardOutline" />
        </button>
      </div>
    </ion-content>
    ```
  </ion-page>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

import { useRoute, useRouter } from "vue-router";

import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonButtons,
  IonButton,
  IonIcon,
  IonAvatar,
  IonTitle,
  IonContent,
} from "@ionic/vue";

import {
  closeOutline,
  chevronBackOutline,
  chevronForwardOutline,
} from "ionicons/icons";

const route = useRoute();

const router = useRouter();

const historias = ref<any[]>([]);

const token = localStorage.getItem("token") || "";

const historia = computed(() => {
  const id = Number(route.params.id);

  return (
    historias.value.find((item) => item.id === id) || {
      id: 0,
      usuario_id: 0,
      nombre: "",
      avatar: "https://i.pravatar.cc/150?img=12",
      tipo: "texto",
      contenido: "",
      archivo: "",
    }
  );
});

const indiceActual = computed(() => {
  return historias.value.findIndex((item) => item.id === historia.value.id);
});

const tieneHistoriaValida = computed(() => {
  const id = Number(route.params.id);

  return Number.isInteger(id) && historias.value.some((item) => item.id === id);
});

const progreso = ref(0);

const pausada = ref(false);

let intervalo: ReturnType<typeof setInterval> | null = null;

const cargarHistorias = async () => {
  try {
    const formData = new URLSearchParams();

    formData.append("token", token);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/estados_controlador.php?task=obtenerEstados",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: formData.toString(),
      },
    );

    const data = await respuesta.json();

    if (!data.ok) {
      historias.value = [];

      return;
    }

    const historiasContactos = (data.estados || []).map((estado: any) => ({
      id: Number(estado.id),

      usuario_id: Number(estado.usuario_id),

      nombre: `${estado.nombre || ""} ${estado.apellido || ""}`.trim(),

      avatar: estado.foto || "https://i.pravatar.cc/150?img=12",

      tipo: estado.tipo,

      contenido: estado.contenido || "",

      archivo: estado.ruta || estado.archivo || "",

      archivo_id: estado.archivo_id,

      visto: Number(estado.visto) || 0,

      fecha_creacion: estado.fecha_creacion,

      fecha_expiracion: estado.fecha_expiracion,
    }));

    // La consulta general excluye al usuario autenticado; cargamos sus estados aparte.
    const propiosForm = new URLSearchParams();
    propiosForm.append("token", token);
    const respuestaPropios = await fetch(
      "http://localhost/new%20app/api/controladores/estados_controlador.php?task=obtenerMisEstados",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: propiosForm.toString(),
      },
    );
    const dataPropios = await respuestaPropios.json();
    const historiasPropias = dataPropios.ok
      ? (dataPropios.estados || []).map((estado: any) => ({
          id: Number(estado.id),
          usuario_id: Number(estado.usuario_id),
          nombre: `${estado.nombre || ""} ${estado.apellido || ""}`.trim(),
          avatar: estado.foto || "https://i.pravatar.cc/150?img=12",
          tipo: estado.tipo,
          contenido: estado.contenido || "",
          archivo: estado.ruta || estado.archivo || "",
          archivo_id: estado.archivo_id,
          visto: 0,
          esMia: true,
          fecha_creacion: estado.fecha_creacion,
          fecha_expiracion: estado.fecha_expiracion,
        }))
      : [];

    const idSolicitado = Number(route.params.id);
    const esHistoriaPropia = historiasPropias.some(
      (item: any) => item.id === idSolicitado,
    );
    const lista = esHistoriaPropia ? historiasPropias : historiasContactos;
    const indice = lista.findIndex((item: any) => item.id === idSolicitado);
    historias.value = indice >= 0 ? lista : historiasContactos;
  } catch (error) {
    console.error("Error al cargar historias:", error);

    historias.value = [];
  }
};

const marcarVisto = async () => {
  if (!historia.value.id || !token) {
    return;
  }

  try {
    const formData = new URLSearchParams();

    formData.append("token", token);

    formData.append("estado_id", String(historia.value.id));

    await fetch(
      "http://localhost/new%20app/api/controladores/estados_controlador.php?task=marcarVisto",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: formData.toString(),
      },
    );
  } catch (error) {
    console.error("Error al marcar historia como vista:", error);
  }
};

const alternarPausa = () => {
  pausada.value = !pausada.value;
};

const iniciarProgreso = () => {
  detenerProgreso();

  if (!tieneHistoriaValida.value) {
    return;
  }

  progreso.value = 0;

  pausada.value = false;

  marcarVisto();

  intervalo = setInterval(() => {
    if (pausada.value) {
      return;
    }

    progreso.value += 1;

    if (progreso.value >= 100) {
      detenerProgreso();

      siguienteHistoria();
    }
  }, 50);
};

const detenerProgreso = () => {
  if (intervalo) {
    clearInterval(intervalo);

    intervalo = null;
  }
};

const cerrarHistorias = () => {
  detenerProgreso();

  router.push("/historias");
};

const siguienteHistoria = () => {
  if (!tieneHistoriaValida.value) {
    router.push("/historias");

    return;
  }

  const indice = indiceActual.value;

  if (indice === -1 || indice >= historias.value.length - 1) {
    router.push("/historias");

    return;
  }

  const siguiente = historias.value[indice + 1];

  router.push(`/historia/${siguiente.id}`);
};

const historiaAnterior = () => {
  if (!tieneHistoriaValida.value) {
    router.push("/historias");

    return;
  }

  const indice = indiceActual.value;

  if (indice <= 0) {
    router.push("/historias");

    return;
  }

  const anterior = historias.value[indice - 1];

  router.push(`/historia/${anterior.id}`);
};

watch(
  () => historia.value.id,
  () => {
    if (tieneHistoriaValida.value) {
      iniciarProgreso();
    }
  },
);

onMounted(async () => {
  await cargarHistorias();

  if (!tieneHistoriaValida.value) {
    router.replace("/historias");

    return;
  }

  iniciarProgreso();
});

onUnmounted(() => {
  detenerProgreso();
});
</script>

<style scoped>
.historia-page {
  --ion-background-color: #090d16;
  background: #090d16;
}

.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  position: relative;
  z-index: 20;
}

.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.78);
  --border-width: 0;
}

.glass-header ion-title {
  color: #ffffff;
  font-size: 17px;
  font-weight: 650;
}

.avatar-header {
  width: 36px;
  height: 36px;
  margin-left: 6px;
  margin-right: 8px;
  padding: 2px;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.25);
}

.avatar-header img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #090d16;
}

.boton-cerrar {
  --color: #ffffff;
  --padding-start: 10px;
  --padding-end: 10px;
  --padding-top: 10px;
  --padding-bottom: 10px;
}

.boton-cerrar ion-icon {
  font-size: 28px;
}

.historia-content {
  --background: #090d16;
}

.historia-container {
  position: relative;
  width: 100%;
  height: 100%;
  background: #090d16;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.glow-emerald,
.glow-cyan {
  position: absolute;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.16;
  pointer-events: none;
  z-index: 0;
}

.glow-emerald {
  top: -120px;
  left: -140px;
  background: #10b981;
}

.glow-cyan {
  bottom: -120px;
  right: -140px;
  background: #06b6d4;
}

.zona-historia {
  position: relative;
  z-index: 2;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 12px;
}

.imagen-wrapper {
  width: min(100%, 500px);
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 24px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow:
    0 20px 60px rgba(0, 0, 0, 0.45),
    0 0 35px rgba(16, 185, 129, 0.05);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.historia-imagen {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 22px;
}

.historia-video {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 22px;
}

.texto-wrapper {
  width: min(100%, 500px);
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 35px;
  border-radius: 24px;
  background: linear-gradient(
    135deg,
    rgba(16, 185, 129, 0.18),
    rgba(6, 182, 212, 0.18)
  );
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow:
    0 20px 60px rgba(0, 0, 0, 0.45),
    0 0 35px rgba(16, 185, 129, 0.05);
}

.historia-texto {
  color: #ffffff;
  font-size: 28px;
  line-height: 1.35;
  font-weight: 600;
  text-align: center;
  white-space: pre-wrap;
  word-break: break-word;
}

.progreso-historias {
  position: absolute;
  top: 12px;
  left: 18px;
  right: 18px;
  z-index: 15;
  display: flex;
  gap: 5px;
}

.progreso {
  flex: 1;
  height: 4px;
  background: rgba(255, 255, 255, 0.18);
  border-radius: 10px;
  overflow: hidden;
  backdrop-filter: blur(8px);
}

.progreso-activo {
  width: 0;
  height: 100%;
  background: linear-gradient(90deg, #10b981, #06b6d4);
  border-radius: 10px;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.45);
}

.progreso-activo.completado {
  width: 100%;
}

.boton-anterior,
.boton-siguiente {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 48px;
  height: 48px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  background: rgba(9, 13, 22, 0.62);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  cursor: pointer;
  transition: all 0.2s ease;
  z-index: 15;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.boton-anterior {
  left: 18px;
}

.boton-siguiente {
  right: 18px;
}

.boton-anterior:hover,
.boton-siguiente:hover {
  background: rgba(16, 185, 129, 0.18);
  border-color: rgba(16, 185, 129, 0.35);
  box-shadow: 0 0 18px rgba(16, 185, 129, 0.18);
  transform: translateY(-50%) scale(1.08);
}

.boton-anterior:active,
.boton-siguiente:active {
  transform: translateY(-50%) scale(0.92);
}

.boton-anterior ion-icon,
.boton-siguiente ion-icon {
  font-size: 27px;
}
</style>
