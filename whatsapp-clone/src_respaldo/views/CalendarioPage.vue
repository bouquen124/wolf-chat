<template>
  <ion-page class="calendario-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button class="boton-navegacion" @click="mesAnterior">
            <ion-icon :icon="chevronBackOutline" />
          </ion-button>
        </ion-buttons>
        <ion-title>{{ nombreMes }} {{ anio }}</ion-title>

        <ion-buttons slot="end">
          <ion-button class="boton-navegacion" @click="mesSiguiente">
            <ion-icon :icon="chevronForwardOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <div class="calendario-card">
          <div class="dias-semana">
            <div v-for="dia in diasSemana" :key="dia">
              {{ dia }}
            </div>
          </div>

          <div class="dias">
            <button
              v-for="dia in diasCalendario"
              :key="dia.key"
              :class="[
                'dia',
                {
                  'otro-mes': !dia.esMesActual,
                  hoy: dia.esHoy,
                  seleccionado: dia.fecha === fechaSeleccionada,
                },
              ]"
              @click="seleccionarDia(dia)"
            >
              <span>{{ dia.numero }}</span>

              <div
                v-if="tieneEventos(dia.fecha)"
                class="indicador-evento"
              ></div>
            </button>
          </div>
        </div>

        <div class="eventos">
          <div class="eventos-header">
            <div>
              <span class="eventos-label">AGENDA</span>
              <h2>{{ fechaFormateada }}</h2>
            </div>

            <ion-button
              class="boton-evento"
              shape="round"
              size="small"
              @click="agregarEvento"
            >
              <ion-icon slot="start" :icon="addOutline" />
              Evento
            </ion-button>
          </div>

          <ion-list v-if="eventosDelDia.length" class="lista-eventos">
            <ion-item
              v-for="evento in eventosDelDia"
              :key="evento.id"
              class="evento-card"
              lines="none"
            >
              <div
                slot="start"
                class="color-evento"
                :style="{ background: evento.color }"
              ></div>

              <ion-label>
                <h2>{{ evento.titulo }}</h2>
                <p>{{ evento.hora }}</p>
              </ion-label>

              <ion-button
                slot="end"
                fill="clear"
                class="boton-eliminar-evento"
                aria-label="Eliminar evento"
                @click="eliminarEvento(evento)"
              >
                <ion-icon :icon="trashOutline" />
              </ion-button>
            </ion-item>
          </ion-list>

          <div v-else class="sin-eventos">
            <div class="icono-sin-eventos">
              <ion-icon :icon="calendarOutline" />
            </div>
            <p>No hay eventos para este día</p>
            <span>Agrega una nueva actividad</span>
          </div>
        </div>
      </div>
    </ion-content>
  </ion-page>
</template>
<script setup lang="ts">
import { computed, ref } from "vue";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonIcon,
  IonContent,
  IonList,
  IonItem,
  IonLabel,
  alertController,
  onIonViewWillEnter,
} from "@ionic/vue";
import {
  addOutline,
  chevronBackOutline,
  chevronForwardOutline,
  calendarOutline,
  trashOutline,
} from "ionicons/icons";
interface Evento {
  id: number;
  fecha: string;
  titulo: string;
  hora: string;
  color: string;
}
interface DiaCalendario {
  key: string;
  fecha: string;
  numero: number;
  esMesActual: boolean;
  esHoy: boolean;
}
const fechaActual = new Date();
const mes = ref(fechaActual.getMonth());
const anio = ref(fechaActual.getFullYear());
const fechaSeleccionada = ref(
  `${fechaActual.getFullYear()}-${String(fechaActual.getMonth() + 1).padStart(2, "0")}-${String(fechaActual.getDate()).padStart(2, "0")}`,
);
const diasSemana = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
const eventos = ref<Evento[]>([]);
const token = localStorage.getItem("token") || "";

const mostrarAlerta = async (header: string, message: string) => {
  const alerta = await alertController.create({
    mode: "ios",
    header,
    message,
    buttons: ["Aceptar"],
  });
  await alerta.present();
};

const cargarEventos = async () => {
  if (!token) {
    await mostrarAlerta(
      "Sesión no válida",
      "Inicia sesión para ver tu calendario.",
    );
    return;
  }

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("anio", String(anio.value));
    datos.append("mes", String(mes.value + 1));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/calendario_controlador.php?task=obtenerEventos",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
        cache: "no-store",
      },
    );
    const resultado = await respuesta.json();
    if (!resultado.ok)
      throw new Error(resultado.error || "No se pudieron cargar los eventos.");

    eventos.value = (
      Array.isArray(resultado.eventos) ? resultado.eventos : []
    ).map((evento: any) => ({
      id: Number(evento.id),
      fecha: String(evento.fecha),
      titulo: String(evento.titulo),
      hora: String(evento.hora || "").slice(0, 5),
      color: String(evento.color || "#6366f1"),
    }));
  } catch (error) {
    console.error("Error al cargar eventos del calendario:", error);
    await mostrarAlerta(
      "No se pudo cargar el calendario",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};

onIonViewWillEnter(cargarEventos);
const nombreMes = computed(() => {
  const fecha = new Date(anio.value, mes.value, 1);
  return fecha
    .toLocaleDateString("es-MX", { month: "long" })
    .replace(/^./, (letra) => letra.toUpperCase());
});
const diasCalendario = computed<DiaCalendario[]>(() => {
  const primerDia = new Date(anio.value, mes.value, 1);
  const ultimoDia = new Date(anio.value, mes.value + 1, 0);
  const primerDiaSemana = primerDia.getDay();
  const totalDias = ultimoDia.getDate();
  const dias: DiaCalendario[] = [];
  const diasMesAnterior = new Date(anio.value, mes.value, 0).getDate();
  for (let i = primerDiaSemana - 1; i >= 0; i--) {
    const numero = diasMesAnterior - i;
    const fecha = new Date(anio.value, mes.value - 1, numero);
    dias.push(crearDia(fecha, false));
  }
  for (let numero = 1; numero <= totalDias; numero++) {
    const fecha = new Date(anio.value, mes.value, numero);
    dias.push(crearDia(fecha, true));
  }
  let siguiente = 1;
  while (dias.length < 42) {
    const fecha = new Date(anio.value, mes.value + 1, siguiente);
    dias.push(crearDia(fecha, false));
    siguiente++;
  }
  return dias;
});
const eventosDelDia = computed(() => {
  return eventos.value.filter(
    (evento) => evento.fecha === fechaSeleccionada.value,
  );
});
const fechaFormateada = computed(() => {
  const [anioSeleccionado, mesSeleccionado, diaSeleccionado] =
    fechaSeleccionada.value.split("-").map(Number);
  const fecha = new Date(
    anioSeleccionado,
    mesSeleccionado - 1,
    diaSeleccionado,
  );
  return fecha
    .toLocaleDateString("es-MX", {
      weekday: "long",
      day: "numeric",
      month: "long",
    })
    .replace(/^./, (letra) => letra.toUpperCase());
});
const crearDia = (fecha: Date, esMesActual: boolean): DiaCalendario => {
  const fechaTexto = formatearFecha(fecha);
  return {
    key: fechaTexto,
    fecha: fechaTexto,
    numero: fecha.getDate(),
    esMesActual,
    esHoy: fecha.toDateString() === fechaActual.toDateString(),
  };
};
const formatearFecha = (fecha: Date) => {
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, "0");
  const dia = String(fecha.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
};
const seleccionarDia = (dia: DiaCalendario) => {
  fechaSeleccionada.value = dia.fecha;
  if (!dia.esMesActual) {
    const [anioDia, mesDia] = dia.fecha.split("-").map(Number);
    mes.value = mesDia - 1;
    anio.value = anioDia;
    void cargarEventos();
  }
};
const mesAnterior = () => {
  if (mes.value === 0) {
    mes.value = 11;
    anio.value--;
  } else {
    mes.value--;
  }
  void cargarEventos();
};
const mesSiguiente = () => {
  if (mes.value === 11) {
    mes.value = 0;
    anio.value++;
  } else {
    mes.value++;
  }
  void cargarEventos();
};
const tieneEventos = (fecha: string) => {
  return eventos.value.some((evento) => evento.fecha === fecha);
};
const agregarEvento = async () => {
  let tituloCapturado = "";
  let horaCapturada = "";
  const formulario = await alertController.create({
    mode: "ios",
    header: "Nuevo evento",
    inputs: [
      {
        name: "titulo",
        type: "text",
        placeholder: "Título del evento",
        attributes: { maxlength: 120 },
      },
      { name: "hora", type: "time", value: "12:00" },
    ],
    buttons: [
      { text: "Cancelar", role: "cancel" },
      {
        text: "Guardar",
        role: "confirm",
        handler: (valores: any) => {
          tituloCapturado = String(valores?.titulo || "").trim();
          horaCapturada = String(valores?.hora || "");
          return true;
        },
      },
    ],
  });
  await formulario.present();
  const { role } = await formulario.onDidDismiss();
  if (role !== "confirm") return;

  if (!tituloCapturado || !horaCapturada) {
    await mostrarAlerta(
      "Faltan datos",
      "Escribe el título y la hora del evento.",
    );
    return;
  }

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("fecha", fechaSeleccionada.value);
    datos.append("titulo", tituloCapturado);
    datos.append("hora", horaCapturada);
    datos.append("color", "#6366f1");

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/calendario_controlador.php?task=crearEvento",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      },
    );
    const resultado = await respuesta.json();
    if (!resultado.ok)
      throw new Error(resultado.error || "No se pudo guardar el evento.");

    await cargarEventos();
    await mostrarAlerta(
      "Evento agregado",
      "El evento se guardó en tu calendario.",
    );
  } catch (error) {
    console.error("Error al guardar evento:", error);
    await mostrarAlerta(
      "No se pudo guardar",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};

const eliminarEvento = async (evento: Evento) => {
  const confirmacion = await alertController.create({
    mode: "ios",
    header: "Eliminar evento",
    message: `¿Quieres eliminar “${evento.titulo}”?`,
    buttons: [
      { text: "Cancelar", role: "cancel" },
      { text: "Eliminar", role: "destructive" },
    ],
  });
  await confirmacion.present();
  const { role } = await confirmacion.onDidDismiss();
  if (role !== "destructive") return;

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("evento_id", String(evento.id));

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/calendario_controlador.php?task=eliminarEvento",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      },
    );
    const resultado = await respuesta.json();
    if (!resultado.ok)
      throw new Error(resultado.error || "No se pudo eliminar el evento.");

    await cargarEventos();
    await mostrarAlerta(
      "Evento eliminado",
      "El evento se eliminó del calendario.",
    );
  } catch (error) {
    console.error("Error al eliminar evento:", error);
    await mostrarAlerta(
      "No se pudo eliminar",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};
</script>
<style scoped>
.calendario-page {
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
  --background: rgba(9, 13, 22, 0.72);
  --border-width: 0;
}
.glass-header ion-title {
  color: #ffffff;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.4px;
}
.boton-navegacion {
  --color: #ffffff;
  --background: transparent;
  --padding-start: 8px;
  --padding-end: 8px;
}
.boton-navegacion ion-icon {
  font-size: 24px;
}
ion-content {
  --background: #090d16;
  position: relative;
  overflow: hidden;
}
.contenido {
  position: relative;
  z-index: 2;
  padding: 12px 14px 100px;
}
.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.17;
  pointer-events: none;
  z-index: 0;
}
.glow-emerald {
  top: 80px;
  left: -140px;
  background: var(--app-accent, #10b981);
}
.glow-cyan {
  top: 390px;
  right: -150px;
  background: var(--app-accent-secondary, #06b6d4);
}
.calendario-card {
  padding: 18px 12px 16px;
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 24px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
}
.dias-semana {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 11px;
  font-weight: 650;
  color: #8993a5;
  margin-bottom: 10px;
  text-transform: uppercase;
}
.dias {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}
.dia {
  position: relative;
  height: 46px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: transparent;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s ease;
}
.dia:active {
  transform: scale(0.92);
}
.dia.otro-mes {
  color: #596273;
  opacity: 0.45;
}
.dia.hoy {
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 0 18px rgba(16, 185, 129, 0.25);
}
.dia.seleccionado:not(.hoy) {
  background: rgba(16, 185, 129, 0.12);
  border-color: rgba(16, 185, 129, 0.28);
  color: #ffffff;
  font-weight: 700;
}
.indicador-evento {
  position: absolute;
  bottom: 5px;
  left: 50%;
  transform: translateX(-50%);
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--app-accent, #10b981);
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.7);
}
.dia.hoy .indicador-evento {
  background: #ffffff;
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.6);
}
.eventos {
  margin-top: 22px;
}
.eventos-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0 4px 14px;
}
.eventos-label {
  display: block;
  margin-bottom: 4px;
  color: var(--app-accent, #10b981);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.2px;
}
.eventos-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 18px;
  font-weight: 650;
}
.boton-evento {
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  --color: #ffffff;
  --border-radius: 18px;
  --box-shadow: 0 6px 18px rgba(16, 185, 129, 0.2);
  font-weight: 600;
  text-transform: none;
}
.lista-eventos {
  background: transparent;
  padding: 0;
}
.evento-card {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --inner-border-width: 0;
  --padding-start: 14px;
  --padding-end: 14px;
  --min-height: 68px;
  margin-bottom: 8px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 18px;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}
.color-evento {
  width: 5px;
  height: 38px;
  margin-right: 12px;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.18);
}
.evento-card h2 {
  margin: 0 0 4px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}
.evento-card p {
  margin: 0;
  color: #8993a5;
  font-size: 13px;
}
.boton-eliminar-evento {
  --color: #f87171;
  margin-inline-end: -6px;
}
.boton-eliminar-evento ion-icon {
  font-size: 19px;
}
.sin-eventos {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 42px 20px;
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 22px;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}
.icono-sin-eventos {
  width: 58px;
  height: 58px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  border-radius: 50%;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.18);
  color: var(--app-accent, #10b981);
}
.sin-eventos ion-icon {
  font-size: 28px;
}
.sin-eventos p {
  margin: 0 0 5px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
}
.sin-eventos span {
  color: #737e91;
  font-size: 12px;
}
</style>
