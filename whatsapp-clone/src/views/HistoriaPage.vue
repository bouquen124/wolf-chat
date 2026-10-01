<template>
  <ion-page class="historia-page">
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

        <ion-title>{{ historia.nombre }}</ion-title>
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
              :class="{ completado: item.id < historia.id }"
              :style="item.id === historia.id ? { width: progreso + '%' } : {}"
            ></div>
          </div>
        </div>

        <div class="zona-historia" @click="alternarPausa">
          <div class="imagen-wrapper">
            <img
              :src="historia.imagen"
              :alt="historia.nombre"
              class="historia-imagen"
            />
          </div>
        </div>

        <button class="boton-anterior" @click="historiaAnterior">
          <ion-icon :icon="chevronBackOutline" />
        </button>

        <button class="boton-siguiente" @click="siguienteHistoria">
          <ion-icon :icon="chevronForwardOutline" />
        </button>
      </div>
    </ion-content>
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
const historias = [
  {
    id: 1,
    nombre: "Juan Pérez",
    avatar: "https://i.pravatar.cc/150?img=12",
    imagen: "https://picsum.photos/id/1011/900/1600",
  },
  {
    id: 2,
    nombre: "María López",
    avatar: "https://i.pravatar.cc/150?img=5",
    imagen: "https://picsum.photos/id/1015/900/1600",
  },
  {
    id: 3,
    nombre: "Carlos Ramírez",
    avatar: "https://i.pravatar.cc/150?img=3",
    imagen: "https://picsum.photos/id/1016/900/1600",
  },
  {
    id: 4,
    nombre: "Ana Martínez",
    avatar: "https://i.pravatar.cc/150?img=9",
    imagen: "https://picsum.photos/id/1025/900/1600",
  },
  {
    id: 5,
    nombre: "Luis Hernández",
    avatar: "https://i.pravatar.cc/150?img=8",
    imagen: "https://picsum.photos/id/1035/900/1600",
  },
  {
    id: 6,
    nombre: "Pedro Sánchez",
    avatar: "https://i.pravatar.cc/150?img=14",
    imagen: "https://picsum.photos/id/1040/900/1600",
  },
];
const historia = computed(() => {
  const id = Number(route.params.id);
  return historias.find((item) => item.id === id) || historias[0];
});
const tieneHistoriaValida = computed(() => {
  const id = Number(route.params.id);
  return Number.isInteger(id) && historias.some((item) => item.id === id);
});
const progreso = ref(0);
const pausada = ref(false);
let intervalo: ReturnType<typeof setInterval> | null = null;
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
  const indiceActual = historias.findIndex(
    (item) => item.id === historia.value.id,
  );
  const siguienteIndice =
    indiceActual === historias.length - 1 ? 0 : indiceActual + 1;
  router.push(`/historia/${historias[siguienteIndice].id}`);
};
const historiaAnterior = () => {
  if (!tieneHistoriaValida.value) {
    router.push("/historias");
    return;
  }
  const indiceActual = historias.findIndex(
    (item) => item.id === historia.value.id,
  );
  const anteriorIndice =
    indiceActual === 0 ? historias.length - 1 : indiceActual - 1;
  router.push(`/historia/${historias[anteriorIndice].id}`);
};
watch(
  () => historia.value.id,
  () => {
    if (tieneHistoriaValida.value) {
      iniciarProgreso();
    }
  },
);
onMounted(() => {
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
