<template>
  <ion-page class="historia-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button class="boton-cerrar" @click="cerrarHistorias">
            <ion-icon :icon="closeOutline" />
          </ion-button>
        </ion-buttons>

        <ion-buttons slot="end" v-if="historia.esMia">
          <ion-button
            class="boton-eliminar"
            :disabled="eliminando"
            aria-label="Eliminar historia"
            @click="eliminarHistoriaActual"
          >
            <ion-icon :icon="trashOutline" />
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

        <button
          v-if="historia.esMia"
          class="boton-vistas"
          @click.stop="abrirVistas"
        >
          <ion-icon :icon="eyeOutline" />
          <span>{{ historia.total_vistas ?? 0 }} vistas</span>
        </button>
      </div>
    </ion-content>

    <ion-modal
      class="modal-vistas-sheet"
      :is-open="mostrarVistas"
      :initial-breakpoint="0.5"
      :breakpoints="[0, 0.5, 0.85]"
      :handle="true"
      @didDismiss="cerrarVistas"
    >
      <ion-header class="glass-header">
        <ion-toolbar>
          <ion-buttons slot="start">
            <ion-button @click="cerrarVistas">Cerrar</ion-button>
          </ion-buttons>
          <ion-title>Visto por</ion-title>
        </ion-toolbar>
      </ion-header>
      <ion-content class="modal-vistas">
        <div v-if="cargandoVistas" class="estado-vistas-vacio">Cargando...</div>
        <ion-list v-else-if="vistas.length" class="lista-vistas">
          <ion-item v-for="vista in vistas" :key="vista.id" lines="none">
            <ion-avatar slot="start">
              <img :src="vista.foto || avatarPredeterminado" :alt="vista.nombre" />
            </ion-avatar>
            <ion-label>
              <h2>{{ `${vista.nombre || ""} ${vista.apellido || ""}`.trim() }}</h2>
              <p>{{ vista.fecha_visto }}</p>
            </ion-label>
          </ion-item>
        </ion-list>
        <div v-else class="estado-vistas-vacio">Todavía nadie ha visto esta historia.</div>
      </ion-content>
    </ion-modal>
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
  IonModal,
  IonList,
  IonItem,
  IonLabel,
  alertController,
} from "@ionic/vue";

import {
  closeOutline,
  chevronBackOutline,
  chevronForwardOutline,
  eyeOutline,
  trashOutline,
} from "ionicons/icons";

const route = useRoute();

const router = useRouter();

const historias = ref<any[]>([]);
const vistas = ref<any[]>([]);
const mostrarVistas = ref(false);
const cargandoVistas = ref(false);
const eliminando = ref(false);
const avatarPredeterminado = "https://i.pravatar.cc/150?img=12";

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

      avatar: estado.foto || avatarPredeterminado,

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
          avatar: estado.foto || avatarPredeterminado,
          tipo: estado.tipo,
          contenido: estado.contenido || "",
          archivo: estado.ruta || estado.archivo || "",
          archivo_id: estado.archivo_id,
          visto: 0,
          esMia: true,
          total_vistas: Number(estado.total_vistas) || 0,
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

const abrirVistas = async () => {
  if (!historia.value.esMia || !historia.value.id || cargandoVistas.value) return;

  pausada.value = true;
  mostrarVistas.value = true;
  cargandoVistas.value = true;
  vistas.value = [];

  try {
    const formData = new URLSearchParams();
    formData.append("token", token);
    formData.append("estado_id", String(historia.value.id));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/estados_controlador.php?task=obtenerVistas",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData.toString(),
      },
    );
    const data = await respuesta.json();
    if (!data.ok) throw new Error(data.error || "No se pudieron cargar las vistas.");
    vistas.value = Array.isArray(data.vistas) ? data.vistas : [];
  } catch (error: any) {
    console.error("Error al cargar quién vio la historia:", error);
    window.alert(error.message || "No se pudieron cargar las vistas.");
    cerrarVistas();
  } finally {
    cargandoVistas.value = false;
  }
};

const cerrarVistas = () => {
  mostrarVistas.value = false;
  pausada.value = false;
};

const eliminarHistoriaActual = async () => {
  if (!historia.value.esMia || !historia.value.id || eliminando.value) return;

  const confirmacion = await alertController.create({
    mode: "ios",
    header: "Eliminar historia",
    message: "¿Seguro que quieres eliminar esta historia?",
    buttons: [
      { text: "Cancelar", role: "cancel" },
      { text: "Eliminar", role: "destructive" },
    ],
  });
  await confirmacion.present();
  const { role } = await confirmacion.onDidDismiss();
  if (role !== "destructive") return;

  eliminando.value = true;
  const idEliminado = historia.value.id;
  const indiceEliminado = indiceActual.value;

  try {
    const formData = new URLSearchParams();
    formData.append("token", token);
    formData.append("estado_id", String(idEliminado));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/estados_controlador.php?task=eliminarEstado",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData.toString(),
      },
    );
    const data = await respuesta.json();
    if (!data.ok) throw new Error(data.error || "No se pudo eliminar la historia.");

    detenerProgreso();
    const restantes = historias.value.filter((item) => item.id !== idEliminado);
    historias.value = restantes;

    const alerta = await alertController.create({
      mode: "ios",
      header: "Historia eliminada",
      message: "La historia se eliminó correctamente.",
      buttons: ["Aceptar"],
    });
    await alerta.present();
    await alerta.onDidDismiss();

    if (restantes.length) {
      const siguiente = restantes[Math.min(indiceEliminado, restantes.length - 1)];
      await router.replace(`/historia/${siguiente.id}`);
    } else {
      await router.replace("/historias");
    }
  } catch (error: any) {
    console.error("Error al eliminar historia:", error);
    const alerta = await alertController.create({
      mode: "ios",
      header: "No se pudo eliminar",
      message: error.message || "Inténtalo de nuevo.",
      buttons: ["Aceptar"],
    });
    await alerta.present();
  } finally {
    eliminando.value = false;
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
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
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

.boton-eliminar {
  --color: #fff;
}

.boton-eliminar ion-icon {
  font-size: 21px;
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
  background: var(--app-accent, #10b981);
}

.glow-cyan {
  bottom: -120px;
  right: -140px;
  background: var(--app-accent-secondary, #06b6d4);
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
  background: linear-gradient(90deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
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

.boton-vistas {
  position: absolute;
  z-index: 16;
  bottom: 22px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 22px;
  color: #fff;
  background: rgba(9, 13, 22, 0.76);
  font-size: 14px;
  backdrop-filter: blur(12px);
}

.boton-vistas ion-icon {
  font-size: 20px;
}

.modal-vistas {
  --background: #090d16;
}

.lista-vistas {
  background: transparent;
  padding: 8px 12px;
}

.lista-vistas ion-item {
  --background: rgba(255, 255, 255, 0.045);
  --color: #fff;
  --border-radius: 14px;
  margin-bottom: 8px;
}

.lista-vistas ion-avatar {
  width: 44px;
  height: 44px;
}

.lista-vistas h2 {
  color: #fff;
  font-size: 15px;
  font-weight: 600;
}

.lista-vistas p,
.estado-vistas-vacio {
  color: #aab3c1;
  font-size: 13px;
}

.estado-vistas-vacio {
  padding: 28px 18px;
  text-align: center;
}
</style>
