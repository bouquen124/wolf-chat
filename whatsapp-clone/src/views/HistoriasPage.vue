<template>
  <ion-page class="historias-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-title>Historias</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <!-- SECCIÓN MI HISTORIA (ARRIBA) -->
        <ion-list class="mi-historia-list">
          <ion-list-header>
            <ion-label>Mi estado</ion-label>
          </ion-list-header>

          <div class="mi-historia-card">
            <!-- AVATAR Y BOTÓN DE VER/AGREGAR -->
            <div class="mi-avatar-wrapper" @click="abrirMiHistoria">
              <ion-avatar :class="['mi-avatar', { 'con-historias': misHistorias.length }]">
                <img v-if="miAvatar" :src="miAvatar" alt="Mi Avatar" />
              </ion-avatar>
            </div>

            <!-- TEXTO E INFORMACIÓN -->
            <div class="info-historia" @click="
              misHistorias.length ? abrirMiHistoria() : agregarHistoria()
              ">
              <h2>Mi historia</h2>
              <p>
                {{
                  misHistorias.length
                    ? `${misHistorias.length} actualización${misHistorias.length > 1 ? "es" : ""}`
                    : "Toca para agregar una actualización"
                }}
              </p>
            </div>

            <!-- Acciones de estado: texto y foto/video -->
            <div class="acciones-mi-historia">
              <ion-button fill="clear" class="btn-accion" aria-label="Crear estado de texto" @click="agregarHistoria">
                <ion-icon :icon="createOutline" />
              </ion-button>
              <ion-button fill="clear" class="btn-accion" aria-label="Crear estado con foto o video"
                :disabled="publicando" @click="seleccionarArchivo">
                <ion-icon :icon="cameraOutline" />
              </ion-button>
            </div>
          </div>
        </ion-list>

        <section v-if="estadosProgramados.length" class="seccion-programados">
          <h3>Historias programadas</h3>
          <article v-for="item in estadosProgramados" :key="item.id" class="programado-item">
            <div>
              <strong>{{ item.tipo === 'texto' ? (item.contenido || 'Historia de texto') : item.nombre_original
                }}</strong>
              <small>{{ formatearFecha(item.programar_en) }} · {{ item.duracion_horas }} h{{
                Number(item.repetir_semanal) ? ' · Semanal' : '' }}</small>
            </div>
            <ion-button fill="clear" color="danger" :disabled="publicando"
              @click="cancelarProgramado(item.id)">Cancelar</ion-button>
          </article>
        </section>

        <!-- SECCIÓN HISTORIAS DE LOS DEMÁS (EXCLUYE SIEMPRE MI USUARIO) -->
        <div class="seccion-historias" v-if="historiasNoVistas.length">
          <h3>Historias recientes</h3>

          <div class="historias">
            <div v-for="historia in historiasNoVistas" :key="historia.usuario_id" class="historia"
              @click="abrirHistoria(historia)">
              <div class="historia-avatar no-vista">
                <img v-if="historia.avatar" :src="historia.avatar" />
              </div>

              <span>{{ historia.nombre }}</span>
            </div>
          </div>
        </div>

        <div class="seccion-historias" v-if="historiasVistas.length">
          <h3>Historias vistas</h3>

          <div class="historias">
            <div v-for="historia in historiasVistas" :key="historia.usuario_id" class="historia"
              @click="abrirHistoria(historia)">
              <div class="historia-avatar vista">
                <img v-if="historia.avatar" :src="historia.avatar" />
              </div>

              <span>{{ historia.nombre }}</span>
            </div>
          </div>
        </div>

        <div v-if="!historiasNoVistas.length && !historiasVistas.length" class="sin-historias">
          <span>No hay historias de otros usuarios</span>
        </div>
      </div>

      <input ref="entradaArchivo" class="entrada-archivo" type="file" accept="image/*,video/*"
        @change="publicarArchivo" />

      <!-- MODAL PARA CREAR ESTADO -->
      <ion-modal :is-open="mostrarModal" @didDismiss="cerrarModal">
        <ion-header class="glass-header">
          <ion-toolbar>
            <ion-buttons slot="start">
              <ion-button :disabled="publicando" @click="cerrarModal">
                Cancelar
              </ion-button>
            </ion-buttons>

            <ion-title>{{ archivoSeleccionado ? 'Nueva historia multimedia' : 'Nueva historia' }}</ion-title>

            <ion-buttons slot="end">
              <ion-button
                :disabled="publicando || (!contenidoHistoria.trim() && !archivoSeleccionado) || (programarHistoria && !fechaHoraProgramada)"
                @click="publicarHistoria">
                {{ publicando ? "Guardando..." : programarHistoria ? "Programar" : "Publicar" }}
              </ion-button>
            </ion-buttons>
          </ion-toolbar>
        </ion-header>

        <ion-content class="modal-historia">
          <div class="contenido-modal">
            <ion-item class="campo-historia" lines="none">
              <ion-textarea v-if="!archivoSeleccionado" v-model="contenidoHistoria"
                placeholder="Escribe una actualización..." :auto-grow="true" :rows="6" :maxlength="700"></ion-textarea>
              <div v-else class="archivo-seleccionado">{{ archivoSeleccionado.name }}</div>
            </ion-item>
            <ion-item lines="none">
              <ion-label>Programar publicación</ion-label>
              <ion-toggle v-model="programarHistoria" />
            </ion-item>
            <template v-if="programarHistoria">
              <ion-item lines="none"><ion-label position="stacked">Fecha y hora</ion-label><ion-input
                  v-model="fechaHoraProgramada" type="datetime-local" /></ion-item>
              <ion-item lines="none"><ion-label position="stacked">Duración (24 a 168 horas)</ion-label><ion-input
                  v-model.number="duracionHoras" type="number" min="24" max="168" /></ion-item>
              <ion-item lines="none"><ion-label>Repetir semanalmente</ion-label><ion-toggle
                  v-model="repetirSemanal" /></ion-item>
              <ion-item v-if="repetirSemanal" lines="none"><ion-label position="stacked">Repetir
                  hasta</ion-label><ion-input v-model="repetirHasta" type="date" /></ion-item>
              <p v-if="repetirSemanal" class="ayuda-programacion">Se repetirá el mismo día y hora cada semana hasta la
                fecha indicada.</p>
            </template>
          </div>
        </ion-content>
      </ion-modal>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonList,
  IonListHeader,
  IonLabel,
  IonAvatar,
  IonModal,
  IonButtons,
  IonButton,
  IonTextarea,
  IonInput,
  IonToggle,
  IonIcon,
  onIonViewDidLeave,
  onIonViewWillEnter,
} from "@ionic/vue";
import { cameraOutline, createOutline } from "ionicons/icons";

const router = useRouter();

const misHistorias = ref<any[]>([]);
const historiasOtros = ref<any[]>([]);

const miAvatar = ref((() => {
  try {
    return JSON.parse(localStorage.getItem("usuario") || "{}").foto || "";
  } catch {
    return "";
  }
})());
const token = localStorage.getItem("token") || "";

// Función inteligente para obtener el ID de usuario logueado desde localStorage
const obtenerMiUsuarioId = (): number | null => {
  const directo =
    localStorage.getItem("usuario_id") ||
    localStorage.getItem("id") ||
    localStorage.getItem("user_id");

  if (directo && !isNaN(Number(directo))) {
    return Number(directo);
  }

  try {
    const usuarioObj = JSON.parse(
      localStorage.getItem("usuario") || localStorage.getItem("user") || "{}",
    );
    if (usuarioObj.id) return Number(usuarioObj.id);
    if (usuarioObj.usuario_id) return Number(usuarioObj.usuario_id);
  } catch (e) {
    // Si falla el parseo, continuar
  }

  return null;
};

const miUsuarioId = ref<number | null>(obtenerMiUsuarioId());

const mostrarModal = ref(false);
const contenidoHistoria = ref("");
const publicando = ref(false);
const archivoSeleccionado = ref<File | null>(null);
const programarHistoria = ref(false);
const fechaHoraProgramada = ref("");
const duracionHoras = ref(24);
const repetirSemanal = ref(false);
const repetirHasta = ref("");
const estadosProgramados = ref<any[]>([]);
const entradaArchivo = ref<HTMLInputElement | null>(null);
let intervaloActualizacion: ReturnType<typeof setInterval> | null = null;
let cargandoHistorias = false;

// Agrupar historias de otros usuarios (FILTRANDO MI PROPIO USUARIO)
const historiasPorUsuario = computed(() => {
  const grupos: Record<number, any> = {};

  historiasOtros.value.forEach((historia) => {
    const usuarioId = Number(historia.usuario_id);

    // 🛑 FILTRO 1: Si coincide con miUsuarioId, NUNCA se muestra en la sección de amigos
    if (miUsuarioId.value && usuarioId === miUsuarioId.value) {
      return;
    }

    // 🛑 FILTRO 2: Si por alguna razón la historia está registrada en misHistorias, la excluimos
    const esMia = misHistorias.value.some(
      (m) => Number(m.id) === Number(historia.id),
    );
    if (esMia) {
      return;
    }

    if (!grupos[usuarioId]) {
      grupos[usuarioId] = {
        usuario_id: usuarioId,
        nombre: historia.nombre,
        avatar: historia.avatar,
        estados: [],
        tieneNoVistas: false,
      };
    }

    grupos[usuarioId].estados.push(historia);

    if (Number(historia.visto) === 0) {
      grupos[usuarioId].tieneNoVistas = true;
    }
  });

  return Object.values(grupos);
});

const historiasNoVistas = computed(() => {
  return historiasPorUsuario.value.filter((historia) => historia.tieneNoVistas);
});

const historiasVistas = computed(() => {
  return historiasPorUsuario.value.filter(
    (historia) => !historia.tieneNoVistas,
  );
});

// Cargar mis propias historias desde la API
const cargarMisHistorias = async () => {
  try {
    const formData = new URLSearchParams();
    formData.append("token", token);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/estados_controlador.php?task=obtenerMisEstados",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: formData.toString(),
      },
    );

    const data = await respuesta.json();

    if (data.ok && data.usuario_id) {
      miUsuarioId.value = Number(data.usuario_id);
    }

    if (data.ok && Array.isArray(data.estados) && data.estados.length > 0) {
      misHistorias.value = data.estados.map((estado: any) => ({
        id: Number(estado.id),
        usuario_id: Number(estado.usuario_id),
        nombre: `${estado.nombre || ""} ${estado.apellido || ""}`.trim(),
        avatar: estado.foto || miAvatar.value,
        visto: Number(estado.visto) || 0,
        tipo: estado.tipo,
        contenido: estado.contenido || "",
        archivo_id: estado.archivo_id,
        archivo: estado.ruta || estado.archivo || "",
        fecha_creacion: estado.fecha_creacion,
        fecha_expiracion: estado.fecha_expiracion,
        esMia: true,
      }));

      // Guardamos el ID detectado
      miUsuarioId.value = Number(misHistorias.value[0].usuario_id);

      if (misHistorias.value[0].avatar) {
        miAvatar.value = misHistorias.value[0].avatar;
      }
    } else {
      misHistorias.value = [];
    }
  } catch (error) {
    console.error("Error al cargar mis historias:", error);
    misHistorias.value = [];
  }
};

// Cargar historias generales
const cargarHistoriasOtros = async () => {
  try {
    const formData = new URLSearchParams();
    formData.append("token", token);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/estados_controlador.php?task=obtenerEstados",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: formData.toString(),
      },
    );

    const data = await respuesta.json();

    if (!data.ok || !Array.isArray(data.estados)) {
      historiasOtros.value = [];
      return;
    }

    const estadosMapeados = data.estados.map((estado: any) => ({
      id: Number(estado.id),
      usuario_id: Number(estado.usuario_id),
      nombre: `${estado.nombre || ""} ${estado.apellido || ""}`.trim(),
      avatar: estado.foto || "",
      visto: Number(estado.visto) || 0,
      tipo: estado.tipo,
      contenido: estado.contenido || "",
      archivo_id: estado.archivo_id,
      archivo: estado.ruta || estado.archivo || "",
      fecha_creacion: estado.fecha_creacion,
      fecha_expiracion: estado.fecha_expiracion,
      esMia: false,
    }));

    // Esta lista solo contiene estados de contactos. Nunca se rescatan estados propios aquí.
    historiasOtros.value = estadosMapeados.filter((estado: any) => {
      if (miUsuarioId.value && estado.usuario_id === miUsuarioId.value)
        return false;
      return !misHistorias.value.some(
        (mia: any) => Number(mia.id) === Number(estado.id),
      );
    });
  } catch (error) {
    console.error("Error al cargar historias de otros:", error);
    historiasOtros.value = [];
  }
};

const cargarTodo = async () => {
  if (cargandoHistorias) return;
  cargandoHistorias = true;
  try {
    // Primero mis historias para obtener mi usuario_id; después las de contactos.
    await cargarMisHistorias();
    await cargarHistoriasOtros();
    await cargarProgramados();
  } finally {
    cargandoHistorias = false;
  }
};

const abrirMiHistoria = () => {
  if (misHistorias.value.length) {
    router.push(`/historia/${misHistorias.value[0].id}`);
  } else {
    agregarHistoria();
  }
};

const abrirHistoria = (grupo: any) => {
  const estadoNoVisto = grupo.estados.find(
    (estado: any) => Number(estado.visto) === 0,
  );

  const estadoAbrir = estadoNoVisto || grupo.estados[0];

  if (!estadoAbrir) {
    return;
  }

  router.push(`/historia/${estadoAbrir.id}`);
};

const agregarHistoria = () => {
  contenidoHistoria.value = "";
  archivoSeleccionado.value = null;
  programarHistoria.value = false;
  fechaHoraProgramada.value = "";
  duracionHoras.value = 24;
  repetirSemanal.value = false;
  repetirHasta.value = "";
  mostrarModal.value = true;
};

const cargarProgramados = async () => {
  try {
    const body = new URLSearchParams({ token });
    const respuesta = await fetch("https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/estados_controlador.php?task=obtenerProgramados", {
      method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString(), cache: "no-store",
    });
    const data = await respuesta.json();
    estadosProgramados.value = data.ok && Array.isArray(data.programados) ? data.programados : [];
  } catch (error) {
    console.error("Error al cargar las historias programadas:", error);
  }
};

const formatearFecha = (valor: string) => new Date(valor.replace(" ", "T")).toLocaleString();

const cancelarProgramado = async (id: number) => {
  try {
    const body = new URLSearchParams({ token, programado_id: String(id) });
    const respuesta = await fetch("https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/estados_controlador.php?task=cancelarProgramado", {
      method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString(),
    });
    const data = await respuesta.json();
    if (!data.ok) throw new Error(data.error || "No se pudo cancelar la programación.");
    await cargarProgramados();
  } catch (error: any) {
    window.alert(error.message || "No se pudo cancelar la programación.");
  }
};

const seleccionarArchivo = () => {
  entradaArchivo.value?.click();
};

const publicarArchivo = (evento: Event) => {
  const input = evento.target as HTMLInputElement;
  const archivo = input.files?.[0];
  if (!archivo || publicando.value) return;

  const esVideo = archivo.type.startsWith("video/");
  if (!esVideo && !archivo.type.startsWith("image/")) {
    window.alert("Selecciona una imagen o un video.");
    input.value = "";
    return;
  }

  const limite = esVideo ? 20 * 1024 * 1024 : 10 * 1024 * 1024;
  if (archivo.size > limite) {
    window.alert(
      esVideo ? "El video supera 20 MB." : "La imagen supera 10 MB.",
    );
    input.value = "";
    return;
  }

  archivoSeleccionado.value = archivo;
  contenidoHistoria.value = "";
  programarHistoria.value = false;
  duracionHoras.value = 24;
  repetirSemanal.value = false;
  mostrarModal.value = true;
  input.value = "";
};

const cerrarModal = () => {
  if (publicando.value) {
    return;
  }

  mostrarModal.value = false;
  contenidoHistoria.value = "";
  archivoSeleccionado.value = null;
  programarHistoria.value = false;
  fechaHoraProgramada.value = "";
  duracionHoras.value = 24;
  repetirSemanal.value = false;
  repetirHasta.value = "";
};

const publicarHistoria = async () => {
  const contenido = contenidoHistoria.value.trim();
  const archivo = archivoSeleccionado.value;

  if ((!contenido && !archivo) || publicando.value) {
    return;
  }
  if (programarHistoria.value && !fechaHoraProgramada.value) {
    window.alert("Elige fecha y hora para publicar.");
    return;
  }
  if (programarHistoria.value && (!Number.isInteger(Number(duracionHoras.value)) || duracionHoras.value < 24 || duracionHoras.value > 168)) {
    window.alert("La duración debe ser de 24 a 168 horas.");
    return;
  }
  if (programarHistoria.value && repetirSemanal.value && !repetirHasta.value) {
    window.alert("Elige hasta qué fecha se repetirá.");
    return;
  }

  publicando.value = true;

  try {
    const formData = new FormData();
    formData.append("token", token);
    formData.append("tipo", archivo ? (archivo.type.startsWith("video/") ? "video" : "imagen") : "texto");
    if (contenido) formData.append("contenido", contenido);
    if (archivo) formData.append("archivo", archivo);
    if (programarHistoria.value) {
      const fecha = new Date(fechaHoraProgramada.value);
      if (Number.isNaN(fecha.getTime()) || fecha.getTime() <= Date.now()) throw new Error("Elige una fecha y hora futuras.");
      formData.append("programar", "1");
      formData.append("fechas_programadas", JSON.stringify([fechaHoraProgramada.value.replace("T", " ") + ":00"]));
      formData.append("duracion_horas", String(duracionHoras.value));
      formData.append("repetir_semanal", repetirSemanal.value ? "1" : "0");
      if (repetirSemanal.value) formData.append("repetir_hasta", repetirHasta.value);
    }

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/estados_controlador.php?task=crearEstado",
      { method: "POST", body: formData },
    );

    const data = await respuesta.json();

    if (!data.ok) {
      window.alert(data.error || "No se pudo publicar la historia.");
      return;
    }

    mostrarModal.value = false;
    contenidoHistoria.value = "";
    archivoSeleccionado.value = null;
    const fueProgramada = programarHistoria.value;
    programarHistoria.value = false;
    fechaHoraProgramada.value = "";
    duracionHoras.value = 24;
    repetirSemanal.value = false;
    repetirHasta.value = "";

    await cargarTodo();

    window.alert(fueProgramada ? "Historia programada." : "Historia publicada.");
  } catch (error: any) {
    console.error("Error al publicar historia:", error);
    window.alert(error.message || "No se pudo publicar la historia.");
  } finally {
    publicando.value = false;
  }
};

onIonViewWillEnter(() => {
  cargarTodo();
  // Actualiza estados nuevos y marcas de visto sin recargar manualmente.
  if (!intervaloActualizacion) {
    intervaloActualizacion = setInterval(cargarTodo, 1000);
  }
});

onIonViewDidLeave(() => {
  if (intervaloActualizacion) {
    clearInterval(intervaloActualizacion);
    intervaloActualizacion = null;
  }
});
</script>

<style scoped>
.historias-page {
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
  top: 330px;
  right: -130px;
  background: var(--app-accent-secondary, #06b6d4);
}

.mi-historia-list {
  background: transparent;
  padding: 0;
}

.mi-historia-list ion-list-header {
  padding: 10px 4px 12px;
  min-height: auto;
}

.mi-historia-list ion-list-header ion-label {
  color: #ffffff;
  font-size: 15px;
  font-weight: 650;
}

.mi-historia-card {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  min-height: 76px;
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 20px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
}

.mi-avatar-wrapper {
  position: relative;
  width: 52px;
  height: 52px;
  margin-right: 12px;
  flex-shrink: 0;
  cursor: pointer;
}

.mi-avatar {
  width: 52px;
  height: 52px;
  padding: 2px;
  border-radius: 50%;
  background: #2a3243;
}

.mi-avatar.con-historias {
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.3);
}

.mi-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #090d16;
}

.boton-agregar {
  position: absolute;
  right: -4px;
  bottom: -4px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  color: #ffffff;
  border: 2px solid #090d16;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
  cursor: pointer;
  z-index: 10;
}

.entrada-archivo {
  display: none;
}

.info-historia {
  flex: 1;
  cursor: pointer;
  margin-right: 8px;
}

.info-historia h2 {
  margin: 0 0 4px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 650;
}

.info-historia p {
  margin: 0;
  color: #8993a5;
  font-size: 12px;
}

.acciones-mi-historia {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-ver {
  --color: var(--app-accent, #10b981);
  font-weight: 600;
  font-size: 13px;
}

.btn-accion {
  --color: var(--app-accent, #10b981);
  width: 42px;
  height: 42px;
  --border-radius: 50%;
  --background: rgba(16, 185, 129, 0.12);
}

.btn-accion ion-icon {
  font-size: 22px;
}

.btn-crear {
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  --border-radius: 12px;
  --color: #ffffff;
  font-weight: 600;
  font-size: 12px;
  height: 32px;
}

.seccion-programados {
  margin: 22px 4px 0;
}

.seccion-programados h3 {
  color: #fff;
  font-size: 15px;
  font-weight: 650;
}

.programado-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin: 8px 0;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(255, 255, 255, .05);
  color: #fff;
}

.programado-item div {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.programado-item strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.programado-item small,
.ayuda-programacion {
  color: #8993a5;
}

.archivo-seleccionado {
  padding: 14px;
  color: #fff;
  overflow-wrap: anywhere;
}

.seccion-historias {
  margin-top: 24px;
  padding: 0 4px;
}

.seccion-historias h3 {
  margin: 0 0 14px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 650;
}

.historias {
  display: flex;
  gap: 18px;
  overflow-x: auto;
  padding: 4px 2px 12px;
  scrollbar-width: none;
}

.historias::-webkit-scrollbar {
  display: none;
}

.historia {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 68px;
  gap: 7px;
  cursor: pointer;
}

.historia span {
  font-size: 12px;
  color: #aab3c1;
  text-align: center;
  max-width: 68px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.historia-avatar {
  width: 66px;
  height: 66px;
  padding: 3px;
  border-radius: 50%;
  background: #090d16;
}

.historia-avatar.no-vista {
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 18px rgba(16, 185, 129, 0.22);
}

.historia-avatar.vista {
  background: #4b5563;
  box-shadow: none;
}

.historia-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #090d16;
}

.sin-historias {
  display: flex;
  justify-content: center;
  padding: 50px 20px;
  color: #8993a5;
  font-size: 14px;
}

.modal-historia {
  --background: #090d16;
}

.contenido-modal {
  position: relative;
  z-index: 2;
  padding: 20px 14px;
}

.campo-historia {
  --background: rgba(255, 255, 255, 0.045);
  --color: #ffffff;
  --border-color: transparent;
  --highlight-color-focused: var(--app-accent, #10b981);
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 20px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}

.campo-historia ion-textarea {
  --color: #ffffff;
  --placeholder-color: #8993a5;
  --placeholder-opacity: 1;
}

::v-deep(.modal-wrapper) {
  background: #090d16;
}
</style>
