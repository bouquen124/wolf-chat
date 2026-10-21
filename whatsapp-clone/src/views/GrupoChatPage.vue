<template>
  <ion-page class="grupo-chat-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/grupos" />
        </ion-buttons>
        <div class="header-contacto">
          <button v-if="grupo.rol === 'administrador'" class="avatar-ring-header avatar-cambiable" type="button"
            aria-label="Cambiar foto del grupo" @click="entradaFotoGrupo?.click()">
            <ion-avatar class="avatar-header">
              <img v-if="grupo.foto" :src="grupo.foto" />
            </ion-avatar>
            <span><ion-icon :icon="cameraOutline" /></span>
          </button>
          <div v-else class="avatar-ring-header">
            <ion-avatar class="avatar-header"><img v-if="grupo.foto" :src="grupo.foto" /></ion-avatar>
          </div>
          <ion-title>{{ grupo.nombre }}</ion-title>
        </div>
        <ion-button slot="end" fill="clear" class="boton-integrantes" aria-label="Ver integrantes del grupo"
          @click="abrirIntegrantes">
          <ion-icon :icon="peopleOutline" />
        </ion-button>
        <ion-button slot="end" fill="clear" class="boton-salir" aria-label="Salir del grupo" @click="salirDelGrupo">
          <ion-icon :icon="exitOutline" />
        </ion-button>
      </ion-toolbar>
    </ion-header>

    <input ref="entradaFotoGrupo" class="entrada-foto-grupo" type="file" accept="image/jpeg,image/png,image/webp"
      @change="actualizarFotoGrupo" />

    <ion-modal :is-open="mostrarIntegrantes" :initial-breakpoint="0.6" :breakpoints="[0, 0.6, 0.9]" :handle="true"
      @didDismiss="cerrarIntegrantes">
      <ion-header class="glass-header">
        <ion-toolbar>
          <ion-buttons slot="start">
            <ion-button @click="cerrarIntegrantes">Cerrar</ion-button>
          </ion-buttons>
          <ion-title>Integrantes · {{ integrantes.length }}</ion-title>
        </ion-toolbar>
      </ion-header>
      <ion-content class="integrantes-content">
        <div v-if="cargandoIntegrantes" class="integrantes-mensaje">
          Cargando integrantes...
        </div>
        <div v-else-if="errorIntegrantes" class="integrantes-mensaje">
          {{ errorIntegrantes }}
        </div>
        <ion-list v-else class="integrantes-lista">
          <ion-item v-for="integrante in integrantes" :key="integrante.id" button :detail="false" lines="none"
            @click="abrirPerfilIntegrante(integrante)">
            <ion-avatar slot="start">
              <img v-if="integrante.foto" :src="integrante.foto" :alt="integrante.nombre" />
            </ion-avatar>
            <ion-label>
              <h2>
                {{
                  `${integrante.nombre || ""} ${integrante.apellido || ""}`.trim()
                }}
              </h2>
            </ion-label>
            <ion-badge v-if="integrante.rol === 'administrador'" slot="end">
              Admin
            </ion-badge>
          </ion-item>
        </ion-list>
      </ion-content>
    </ion-modal>

    <PerfilContactoModal :visible="mostrarPerfilIntegrante" :perfil="perfilIntegranteActual"
      @cerrar="cerrarPerfilIntegrante" />

    <ion-content ref="contenido" class="chat-content">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="mensajes">
        <div v-for="mensaje in mensajes" :key="mensaje.id" :class="[
          'mensaje',
          mensaje.mensaje_tipo === 'sistema'
            ? 'mensaje-sistema'
            : esMiMensaje(mensaje.remitente_id)
              ? 'mensaje-yo'
              : 'mensaje-otro',
        ]">
          <div class="burbuja">
            <strong v-if="
              mensaje.mensaje_tipo !== 'sistema' &&
              !esMiMensaje(mensaje.remitente_id)
            ">
              {{ mensaje.nombre }} {{ mensaje.apellido || "" }}
            </strong>

            <span class="texto">{{ mensaje.mensaje }}</span>

            <small>
              {{ formatearHora(mensaje.fecha_envio) }}
              <span v-if="esMiMensaje(mensaje.remitente_id)" class="estado-mensaje"
                :class="{ 'mensaje-leido': mensaje.estado_mensaje === 'leido' }">
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
        <ion-input v-model="nuevoMensaje" placeholder="Escribe un mensaje..." @keyup.enter="enviarMensaje" />

        <ion-button class="send-button" :disabled="enviando" @click="enviarMensaje">
          <ion-icon :icon="sendOutline" />
        </ion-button>
      </div>
    </ion-footer>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref } from "vue";
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
  IonModal,
  IonList,
  IonItem,
  IonLabel,
  IonBadge,
  alertController,
  onIonViewWillEnter,
  onIonViewWillLeave,
} from "@ionic/vue";
import { exitOutline, peopleOutline, sendOutline, cameraOutline } from "ionicons/icons";
import { useRoute, useRouter } from "vue-router";
import PerfilContactoModal from "../components/PerfilContactoModal.vue";

interface Grupo {
  conversacion_id: number;
  nombre: string;
  foto: string | null;
  rol: string;
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

interface Integrante {
  id: number;
  nombre: string;
  apellido: string | null;
  foto: string | null;
  rol: string;
  descripcion?: string | null;
  ultima_conexion?: string | null;
  en_linea?: boolean;
}

const route = useRoute();
const router = useRouter();

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
  rol: "",
});

const nuevoMensaje = ref("");
const mensajes = ref<Mensaje[]>([]);
const enviando = ref(false);
const integrantes = ref<Integrante[]>([]);
const mostrarIntegrantes = ref(false);
const mostrarPerfilIntegrante = ref(false);
const integranteSeleccionado = ref<Integrante | null>(null);
const perfilIntegranteActual = computed(() => {
  const integrante = integranteSeleccionado.value;
  if (!integrante) return null;
  return {
    id: integrante.id,
    nombre: `${integrante.nombre || ""} ${integrante.apellido || ""}`.trim(),
    foto: integrante.foto,
    descripcion: integrante.descripcion,
    ultima_conexion: integrante.ultima_conexion,
    en_linea: integrante.en_linea,
    rol: integrante.rol,
  };
});
const entradaFotoGrupo = ref<HTMLInputElement | null>(null);
const cargandoIntegrantes = ref(false);
const errorIntegrantes = ref("");

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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=obtenerGrupos",
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
          rol: grupoEncontrado.rol,
        };
      }
    }
  } catch (error) {
    console.error("Error al obtener grupo:", error);
  }
};

const abrirIntegrantes = async () => {
  mostrarIntegrantes.value = true;
  cargandoIntegrantes.value = true;
  errorIntegrantes.value = "";

  try {
    const formData = new FormData();
    formData.append("token", token);
    formData.append("conversacion_id", String(conversacionId));

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=obtenerIntegrantesGrupo",
      { method: "POST", body: formData, cache: "no-store" },
    );
    const datos = await respuesta.json();
    if (!datos.ok)
      throw new Error(datos.error || "No se pudieron cargar los integrantes.");

    integrantes.value = Array.isArray(datos.integrantes)
      ? datos.integrantes
      : [];
  } catch (error: any) {
    console.error("Error al cargar integrantes del grupo:", error);
    errorIntegrantes.value =
      error.message || "No se pudieron cargar los integrantes.";
  } finally {
    cargandoIntegrantes.value = false;
  }
};

const cerrarIntegrantes = () => {
  mostrarIntegrantes.value = false;
};

const actualizarFotoGrupo = async (evento: Event) => {
  const input = evento.target as HTMLInputElement;
  const archivo = input.files?.[0];
  if (!archivo) return;
  if (!["image/jpeg", "image/png", "image/webp"].includes(archivo.type) || archivo.size > 5 * 1024 * 1024) {
    const alerta = await alertController.create({ mode: "ios", header: "Foto no válida", message: "Elige una imagen JPG, PNG o WebP de máximo 5 MB.", buttons: ["Aceptar"] });
    await alerta.present();
    input.value = "";
    return;
  }
  try {
    const formData = new FormData();
    formData.append("token", token);
    formData.append("conversacion_id", String(conversacionId));
    formData.append("foto", archivo);
    const respuesta = await fetch("https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=actualizarFotoGrupo", { method: "POST", body: formData, cache: "no-store" });
    const datos = await respuesta.json();
    if (!datos.ok) throw new Error(datos.error || "No se pudo actualizar la foto del grupo.");
    grupo.value.foto = datos.foto;
  } catch (error: any) {
    const alerta = await alertController.create({ mode: "ios", header: "No se pudo cambiar la foto", message: error.message || "Inténtalo de nuevo.", buttons: ["Aceptar"] });
    await alerta.present();
  } finally {
    input.value = "";
  }
};

const abrirPerfilIntegrante = (integrante: Integrante) => {
  integranteSeleccionado.value = integrante;
  mostrarPerfilIntegrante.value = true;
};

const cerrarPerfilIntegrante = () => {
  mostrarPerfilIntegrante.value = false;
  integranteSeleccionado.value = null;
};

const salirDelGrupo = async () => {
  const confirmacion = await alertController.create({
    mode: "ios",
    header: "Salir del grupo",
    message:
      "¿Quieres salir de este grupo? Se avisará a los demás integrantes.",
    buttons: [
      { text: "Cancelar", role: "cancel" },
      { text: "Salir", role: "destructive" },
    ],
  });
  await confirmacion.present();
  const { role } = await confirmacion.onDidDismiss();
  if (role !== "destructive") return;

  try {
    const formData = new FormData();
    formData.append("token", token);
    formData.append("conversacion_id", String(conversacionId));

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=salirGrupo",
      { method: "POST", body: formData, cache: "no-store" },
    );
    const datos = await respuesta.json();
    if (!datos.ok)
      throw new Error(datos.error || "No se pudo salir del grupo.");

    detenerPolling();
    await router.replace("/grupos");
  } catch (error: any) {
    console.error("Error al salir del grupo:", error);
    const alerta = await alertController.create({
      mode: "ios",
      header: "No se pudo salir",
      message: error.message || "Inténtalo de nuevo.",
      buttons: ["Entendido"],
    });
    await alerta.present();
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/mensajes_controlador.php?task=obtenerMensajes",
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
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/mensajes_controlador.php?task=enviarMensaje",
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

.boton-integrantes {
  --color: #ffffff;
}

.boton-integrantes ion-icon {
  font-size: 23px;
}

.boton-salir {
  --color: #ffffff;
}

.boton-salir ion-icon {
  font-size: 22px;
}

.integrantes-content {
  --background: #090d16;
}

.integrantes-lista {
  background: transparent;
  padding: 8px 14px 24px;
}

.integrantes-lista ion-item {
  --background: rgba(255, 255, 255, 0.05);
  --color: #ffffff;
  --border-radius: 16px;
  margin-bottom: 8px;
}

.integrantes-lista ion-avatar {
  width: 44px;
  height: 44px;
}

.integrantes-lista h2 {
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}

.integrantes-lista ion-badge {
  --background: rgba(16, 185, 129, 0.18);
  --color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.28);
}

.integrantes-mensaje {
  padding: 28px 18px;
  color: #aab3c1;
  text-align: center;
}

.avatar-ring-header {
  width: 40px;
  height: 40px;
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
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
  background: var(--app-accent, #10b981);
}

.glow-cyan {
  bottom: 80px;
  right: -140px;
  background: var(--app-accent-secondary, #06b6d4);
}

.mensajes {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px 14px 20px;
  min-height: 100%;
  background: var(--app-chat-wallpaper, #090d16);
  background-size: cover;
  background-position: center;
}

.avatar-cambiable {
  position: relative;
  box-sizing: border-box;
  display: grid;
  place-items: center;
  border: 0;
  padding: 2px;
  background: transparent;
  cursor: pointer;
}

.avatar-cambiable>span {
  position: absolute;
  right: -2px;
  bottom: -2px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: #fff;
  background: var(--app-accent-secondary, #06b6d4);
  border: 2px solid #090d16;
}

.avatar-cambiable>span ion-icon {
  font-size: 11px;
}

.entrada-foto-grupo {
  display: none;
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
  background: linear-gradient(135deg,
      rgba(16, 185, 129, 0.28),
      rgba(6, 182, 212, 0.22));
  border-color: rgba(16, 185, 129, 0.18);
  border-bottom-right-radius: 5px;
}

.mensaje-otro .burbuja {
  background: rgba(255, 255, 255, 0.055);
  border-bottom-left-radius: 5px;
}

.mensaje-sistema {
  justify-content: center;
}

.mensaje-sistema .burbuja {
  max-width: 88%;
  padding: 8px 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  color: #aab3c1;
  background: rgba(255, 255, 255, 0.06);
  text-align: center;
}

.burbuja strong {
  font-size: 12px;
  color: var(--app-accent, #10b981);
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
  color: var(--app-accent-secondary, #06b6d4);
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
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
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
