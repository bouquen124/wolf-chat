<template>
  <ion-page class="grupos-page">
    <ion-header class="glass-header">
      <ion-toolbar> <ion-title>Grupos</ion-title> </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <ion-searchbar
          v-model="busqueda"
          placeholder="Buscar grupo"
          class="searchbar"
        />

        <ion-list class="grupos-list">
          <ion-item
            v-for="grupo in gruposFiltrados"
            :key="grupo.conversacion_id"
            button
            :router-link="`/grupo/${grupo.conversacion_id}`"
            class="grupo-card"
            lines="none"
          >
            <div class="avatar-ring" slot="start">
              <ion-avatar>
                <img v-if="grupo.foto" :src="grupo.foto" />
              </ion-avatar>
            </div>

            <ion-label>
              <h2>{{ grupo.nombre }}</h2>
              <p>
                <template v-if="grupo.mensaje">
                  <strong v-if="grupo.remitente_nombre">
                    {{ grupo.remitente_nombre }}:
                  </strong>
                  {{ grupo.mensaje }}
                </template>
                <template v-else>Sin mensajes en esta conversación</template>
              </p>
            </ion-label>

            <div slot="end" class="grupo-info">
              <span class="hora">
                {{ formatearHora(grupo.fecha_envio) }}
              </span>

              <ion-badge v-if="grupo.no_leidos">
                {{ grupo.no_leidos }}
              </ion-badge>
            </div>
          </ion-item>
        </ion-list>
      </div>

      <ion-fab slot="fixed" vertical="bottom" horizontal="end">
        <ion-fab-button class="fab-neon" @click="abrirCrearGrupo">
          <ion-icon :icon="addOutline" />
        </ion-fab-button>
      </ion-fab>

      <ion-modal
        :is-open="modalCrearGrupo"
        @didDismiss="cerrarCrearGrupo"
        class="modal-grupo"
      >
        <ion-header class="glass-header">
          <ion-toolbar>
            <ion-buttons slot="start">
              <ion-button @click="cerrarCrearGrupo"> Cancelar </ion-button>
            </ion-buttons>

            <ion-title>Crear grupo</ion-title>

            <ion-buttons slot="end">
              <ion-button
                :disabled="
                  !nombreGrupo.trim() ||
                  seleccionados.length === 0 ||
                  creandoGrupo
                "
                @click="crearGrupo"
              >
                Crear
              </ion-button>
            </ion-buttons>
          </ion-toolbar>
        </ion-header>

        <ion-content>
          <div class="modal-contenido">
            <div class="grupo-form">
              <button class="grupo-avatar" type="button" aria-label="Elegir foto del grupo" @click="entradaFotoGrupo?.click()">
                <img v-if="previewFotoGrupo" :src="previewFotoGrupo" alt="Foto del grupo" />
                <ion-icon v-else :icon="peopleOutline" />
                <span class="grupo-avatar-camara"><ion-icon :icon="cameraOutline" /></span>
              </button>
              <input ref="entradaFotoGrupo" class="entrada-foto-grupo" type="file" accept="image/jpeg,image/png,image/webp" @change="seleccionarFotoGrupo" />

              <ion-input
                v-model="nombreGrupo"
                label="Nombre del grupo"
                label-placement="floating"
                fill="outline"
                placeholder="Ej. Equipo de trabajo"
                class="nombre-input"
              />
            </div>

            <div class="seleccion-header">
              <div>
                <h2>Integrantes</h2>
                <p>
                  {{ seleccionados.length }}
                  {{
                    seleccionados.length === 1
                      ? "persona seleccionada"
                      : "personas seleccionadas"
                  }}
                </p>
              </div>
            </div>

            <ion-searchbar
              v-model="busquedaContacto"
              placeholder="Buscar persona"
              class="searchbar contactos-search"
            />

            <ion-list class="contactos-list">
              <ion-item
                v-for="contacto in contactosFiltrados"
                :key="contacto.contacto_id"
                button
                lines="none"
                class="contacto-card"
                @click="alternarSeleccion(contacto.contacto_id)"
              >
                <ion-avatar slot="start">
                  <img v-if="contacto.foto" :src="contacto.foto" />
                </ion-avatar>

                <ion-label>
                  <h2>{{ nombreContacto(contacto) }}</h2>
                  <p>{{ contacto.telefono }}</p>
                </ion-label>

                <div
                  slot="end"
                  class="selector"
                  :class="{
                    seleccionado: seleccionados.includes(contacto.contacto_id),
                  }"
                >
                  <ion-icon
                    v-if="seleccionados.includes(contacto.contacto_id)"
                    :icon="checkmarkOutline"
                  />
                </div>
              </ion-item>
            </ion-list>
          </div>
        </ion-content>
      </ion-modal>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonList,
  IonItem,
  IonAvatar,
  IonLabel,
  IonBadge,
  IonFab,
  IonFabButton,
  IonIcon,
  IonModal,
  IonButtons,
  IonButton,
  IonInput,
  onIonViewWillEnter,
  onIonViewWillLeave,
} from "@ionic/vue";
import { addOutline, peopleOutline, checkmarkOutline, cameraOutline } from "ionicons/icons";
import { useRouter } from "vue-router";

interface Grupo {
  conversacion_id: number;
  nombre: string;
  foto: string | null;
  remitente_id: number | null;
  remitente_nombre: string | null;
  mensaje: string | null;
  mensaje_tipo: string | null;
  fecha_envio: string | null;
  no_leidos: number;
}

interface Contacto {
  id: number;
  contacto_id: number;
  nombre_personalizado: string | null;
  telefono: string;
  nombre: string;
  apellido: string | null;
  foto: string | null;
}

const router = useRouter();

const busqueda = ref("");
const grupos = ref<Grupo[]>([]);
const contactos = ref<Contacto[]>([]);
const busquedaContacto = ref("");
const nombreGrupo = ref("");
const seleccionados = ref<number[]>([]);
const modalCrearGrupo = ref(false);
const creandoGrupo = ref(false);
const entradaFotoGrupo = ref<HTMLInputElement | null>(null);
const fotoGrupo = ref<File | null>(null);
const previewFotoGrupo = ref("");
const token = localStorage.getItem("token") || "";

let intervaloGrupos: ReturnType<typeof setInterval> | null = null;
let cargandoGrupos = false;

const obtenerGrupos = async () => {
  // Evita que se encimen peticiones si el servidor tarda más de 2 s
  if (cargandoGrupos) {
    return;
  }

  cargandoGrupos = true;

  try {
    const formData = new FormData();

    formData.append("token", token);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=obtenerGrupos",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (datos.ok) {
      grupos.value = [...(datos.grupos || [])];
    }
  } catch (error) {
    console.error("Error al obtener grupos:", error);
  } finally {
    cargandoGrupos = false;
  }
};

const obtenerContactos = async () => {
  try {
    const formData = new FormData();

    formData.append("token", token);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/contactos_controlador.php?task=obtenerContactos",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (datos.ok) {
      contactos.value = datos.contactos || [];
    }
  } catch (error) {
    console.error("Error al obtener contactos:", error);
  }
};

const abrirCrearGrupo = async () => {
  limpiarFotoGrupo();
  nombreGrupo.value = "";
  busquedaContacto.value = "";
  seleccionados.value = [];

  await obtenerContactos();

  modalCrearGrupo.value = true;
};

const limpiarFotoGrupo = () => {
  if (previewFotoGrupo.value.startsWith("blob:")) URL.revokeObjectURL(previewFotoGrupo.value);
  previewFotoGrupo.value = "";
  fotoGrupo.value = null;
  if (entradaFotoGrupo.value) entradaFotoGrupo.value.value = "";
};

const seleccionarFotoGrupo = (evento: Event) => {
  const input = evento.target as HTMLInputElement;
  const archivo = input.files?.[0];
  if (!archivo) return;
  if (!["image/jpeg", "image/png", "image/webp"].includes(archivo.type) || archivo.size > 5 * 1024 * 1024) {
    window.alert("Elige una imagen JPG, PNG o WebP de máximo 5 MB.");
    input.value = "";
    return;
  }
  limpiarFotoGrupo();
  fotoGrupo.value = archivo;
  previewFotoGrupo.value = URL.createObjectURL(archivo);
};

const cerrarCrearGrupo = () => {
  if (creandoGrupo.value) {
    return;
  }

  modalCrearGrupo.value = false;
  limpiarFotoGrupo();
};

const alternarSeleccion = (contactoId: number) => {
  const indice = seleccionados.value.indexOf(contactoId);

  if (indice === -1) {
    seleccionados.value.push(contactoId);
  } else {
    seleccionados.value.splice(indice, 1);
  }
};

const nombreContacto = (contacto: Contacto) => {
  if (contacto.nombre_personalizado) {
    return contacto.nombre_personalizado;
  }

  return `${contacto.nombre} ${contacto.apellido || ""}`.trim();
};

const contactosFiltrados = computed(() => {
  const texto = busquedaContacto.value.toLowerCase().trim();

  return contactos.value.filter((contacto) => {
    const nombre = nombreContacto(contacto).toLowerCase();
    const telefono = contacto.telefono.toLowerCase();

    return !texto || nombre.includes(texto) || telefono.includes(texto);
  });
});

const crearGrupo = async () => {
  if (
    !nombreGrupo.value.trim() ||
    seleccionados.value.length === 0 ||
    creandoGrupo.value
  ) {
    return;
  }

  creandoGrupo.value = true;

  try {
    const formData = new FormData();

    formData.append("token", token);
    formData.append("nombre", nombreGrupo.value.trim());
    formData.append("usuarios", JSON.stringify(seleccionados.value));
    if (fotoGrupo.value) formData.append("foto", fotoGrupo.value);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=crearGrupo",
      {
        method: "POST",
        body: formData,
        cache: "no-store",
      },
    );

    const datos = await respuesta.json();

    if (datos.ok) {
      modalCrearGrupo.value = false;
      limpiarFotoGrupo();

      await obtenerGrupos();

      if (datos.conversacion_id) {
        router.push(`/grupo/${datos.conversacion_id}`);
      }
    } else {
      console.error(datos.error || "No se pudo crear el grupo");
      window.alert(datos.error || "No se pudo crear el grupo.");
    }
  } catch (error) {
    console.error("Error al crear grupo:", error);
    window.alert("No se pudo crear el grupo. Revisa tu conexión e inténtalo de nuevo.");
  } finally {
    creandoGrupo.value = false;
  }
};

const formatearHora = (fecha: string | null) => {
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

const gruposFiltrados = computed(() => {
  const texto = busqueda.value.toLowerCase().trim();

  return grupos.value.filter((grupo) =>
    grupo.nombre.toLowerCase().includes(texto),
  );
});

/* ===================== POLLING ===================== */

const iniciarPolling = () => {
  if (intervaloGrupos) {
    return;
  }

  intervaloGrupos = setInterval(() => {
    obtenerGrupos();
  }, 2000);
};

const detenerPolling = () => {
  if (intervaloGrupos) {
    clearInterval(intervaloGrupos);
    intervaloGrupos = null;
  }
};

// Cuando se manda un mensaje desde un grupo, refresca la lista al instante
const alEnviarMensajeGrupo = () => {
  obtenerGrupos();
};

/* ===================== CICLO DE VIDA ===================== */

onMounted(async () => {
  window.addEventListener("grupo-mensaje-enviado", alEnviarMensajeGrupo);

  await obtenerGrupos();
  iniciarPolling();
});

onIonViewWillEnter(async () => {
  await obtenerGrupos();
  iniciarPolling();
});

onIonViewWillLeave(() => {
  detenerPolling();
});

onUnmounted(() => {
  window.removeEventListener("grupo-mensaje-enviado", alEnviarMensajeGrupo);
  detenerPolling();
});
</script>

<style scoped>
.grupos-page {
  --ion-background-color: #090d16;
  background: #090d16;
}

.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}

.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.72);
  --border-width: 0;
}

.glass-header ion-title {
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.5px;
}

ion-content {
  --background: #090d16;
  position: relative;
  overflow: hidden;
}

.contenido {
  position: relative;
  z-index: 2;
  padding: 8px 14px 100px;
}

.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  filter: blur(90px);
  opacity: 0.18;
  pointer-events: none;
  z-index: 0;
}

.glow-emerald {
  top: 70px;
  left: -120px;
  background: var(--app-accent, #10b981);
}

.glow-cyan {
  top: 280px;
  right: -130px;
  background: var(--app-accent-secondary, #06b6d4);
}

.searchbar {
  --background: rgba(255, 255, 255, 0.055);
  --border-radius: 18px;
  --box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.07);
  --color: #ffffff;
  --placeholder-color: #7d8798;
  --icon-color: var(--app-accent, #10b981);
  margin: 4px 0 18px;
  padding: 0;
}

.grupos-list,
.contactos-list {
  background: transparent;
  padding: 0;
}

.grupo-card {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --border-color: transparent;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 76px;
  margin-bottom: 10px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 20px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}

.avatar-ring {
  width: 52px;
  height: 52px;
  padding: 2px;
  margin-right: 12px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.18);
}

.avatar-ring ion-avatar {
  width: 48px;
  height: 48px;
  border: 2px solid #090d16;
}

.grupo-card h2 {
  margin: 0 0 5px;
  color: #ffffff;
  font-size: 16px;
  font-weight: 650;
}

.grupo-card p {
  margin: 0;
  color: #8993a5;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.grupo-card p strong {
  color: var(--app-accent, #10b981);
  font-weight: 600;
}

.grupo-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 7px;
  margin-left: 8px;
}

.hora {
  font-size: 11px;
  color: #697486;
}

.grupo-info ion-badge {
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  --color: #ffffff;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  font-size: 11px;
  font-weight: 700;
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.25);
}

ion-fab {
  margin: 0 10px 14px 0;
}

.fab-neon {
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  --background-activated: linear-gradient(135deg, #059669, #0891b2);
  --color: #ffffff;
  --box-shadow: 0 8px 28px rgba(16, 185, 129, 0.32);
  width: 58px;
  height: 58px;
}

.modal-grupo {
  --background: #090d16;
}

.modal-grupo ion-content {
  --background: #090d16;
}

.modal-contenido {
  padding: 20px 14px 40px;
}

.grupo-form {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 28px;
}

.grupo-avatar {
  position: relative;
  padding: 0;
  border: 0;
  overflow: visible;
  cursor: pointer;
  min-width: 58px;
  width: 58px;
  height: 58px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 24px rgba(16, 185, 129, 0.25);
}

.grupo-avatar > img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.grupo-avatar-camara { position: absolute; right: -3px; bottom: -3px; width: 23px; height: 23px; border: 2px solid #090d16; border-radius: 50%; display: grid; place-items: center; color: #fff; background: var(--app-accent-secondary, #06b6d4); }
.grupo-avatar-camara ion-icon { font-size: 13px; }
.entrada-foto-grupo { display: none; }

.grupo-avatar ion-icon {
  font-size: 28px;
  color: #ffffff;
}

.nombre-input {
  --background: rgba(255, 255, 255, 0.045);
  --color: #ffffff;
  --border-color: rgba(255, 255, 255, 0.09);
  --highlight-color: var(--app-accent, #10b981);
  --border-radius: 16px;
  flex: 1;
}

.seleccion-header {
  margin-bottom: 4px;
}

.seleccion-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 19px;
  font-weight: 700;
}

.seleccion-header p {
  margin: 5px 0 0;
  color: #8993a5;
  font-size: 13px;
}

.contactos-search {
  margin-top: 14px;
}

.contacto-card {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --border-color: transparent;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 68px;
  margin-bottom: 8px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 18px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}

.contacto-card ion-avatar {
  width: 46px;
  height: 46px;
  margin-right: 12px;
}

.contacto-card h2 {
  margin: 0 0 4px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}

.contacto-card p {
  margin: 0;
  color: #8993a5;
  font-size: 12px;
}

.selector {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 2px solid #596273;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: 0.2s ease;
}

.selector.seleccionado {
  border-color: var(--app-accent, #10b981);
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 14px rgba(16, 185, 129, 0.28);
}

.selector ion-icon {
  font-size: 17px;
  color: #ffffff;
}
</style>
