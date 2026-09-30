<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-button @click="mesAnterior">
            <ion-icon :icon="chevronBackOutline" />
          </ion-button>
        </ion-buttons>


    <ion-title>{{ nombreMes }} {{ anio }}</ion-title>

    <ion-buttons slot="end">
      <ion-button @click="mesSiguiente">
        <ion-icon :icon="chevronForwardOutline" />
      </ion-button>
    </ion-buttons>
  </ion-toolbar>
</ion-header>

<ion-content>
  <div class="calendario">
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
            'hoy': dia.esHoy,
            'seleccionado': dia.fecha === fechaSeleccionada
          }
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
      <h2>{{ fechaFormateada }}</h2>

      <ion-button
        shape="round"
        size="small"
        @click="agregarEvento"
      >
        <ion-icon slot="start" :icon="addOutline" />
        Evento
      </ion-button>
    </div>

    <ion-list v-if="eventosDelDia.length">
      <ion-item
        v-for="evento in eventosDelDia"
        :key="evento.id"
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
      </ion-item>
    </ion-list>

    <div v-else class="sin-eventos">
      <ion-icon :icon="calendarOutline" />
      <p>No hay eventos para este día</p>
    </div>
  </div>
</ion-content>


  </ion-page>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
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
  IonLabel
} from '@ionic/vue';

import {
  addOutline,
  chevronBackOutline,
  chevronForwardOutline,
  calendarOutline
} from 'ionicons/icons';

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
  fechaActual.toISOString().split('T')[0]
);

const diasSemana = [
  'Dom',
  'Lun',
  'Mar',
  'Mié',
  'Jue',
  'Vie',
  'Sáb'
];

const eventos = ref<Evento[]>([
  {
    id: 1,
    fecha: fechaActual.toISOString().split('T')[0],
    titulo: 'Reunión de trabajo',
    hora: '10:00',
    color: '#6366f1'
  },
  {
    id: 2,
    fecha: fechaActual.toISOString().split('T')[0],
    titulo: 'Comida con amigos',
    hora: '14:30',
    color: '#25d366'
  },
  {
    id: 3,
    fecha: fechaActual.toISOString().split('T')[0],
    titulo: 'Entrenamiento',
    hora: '19:00',
    color: '#f59e0b'
  }
]);

const nombreMes = computed(() => {
  const fecha = new Date(anio.value, mes.value, 1);

  return fecha.toLocaleDateString('es-MX', {
    month: 'long'
  }).replace(/^./, letra => letra.toUpperCase());
});

const diasCalendario = computed<DiaCalendario[]>(() => {
  const primerDia = new Date(anio.value, mes.value, 1);
  const ultimoDia = new Date(anio.value, mes.value + 1, 0);

  const primerDiaSemana = primerDia.getDay();
  const totalDias = ultimoDia.getDate();

  const dias: DiaCalendario[] = [];

  const diasMesAnterior = new Date(
    anio.value,
    mes.value,
    0
  ).getDate();

  for (let i = primerDiaSemana - 1; i >= 0; i--) {
    const numero = diasMesAnterior - i;
    const fecha = new Date(
      anio.value,
      mes.value - 1,
      numero
    );

    dias.push(crearDia(fecha, false));
  }

  for (let numero = 1; numero <= totalDias; numero++) {
    const fecha = new Date(
      anio.value,
      mes.value,
      numero
    );

    dias.push(crearDia(fecha, true));
  }

  let siguiente = 1;

  while (dias.length < 42) {
    const fecha = new Date(
      anio.value,
      mes.value + 1,
      siguiente
    );

    dias.push(crearDia(fecha, false));
    siguiente++;
  }

  return dias;
});

const eventosDelDia = computed(() => {
  return eventos.value.filter(
    evento => evento.fecha === fechaSeleccionada.value
  );
});

const fechaFormateada = computed(() => {
  const [anioSeleccionado, mesSeleccionado, diaSeleccionado] =
    fechaSeleccionada.value.split('-').map(Number);

  const fecha = new Date(
    anioSeleccionado,
    mesSeleccionado - 1,
    diaSeleccionado
  );

  return fecha.toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  }).replace(/^./, letra => letra.toUpperCase());
});

const crearDia = (
  fecha: Date,
  esMesActual: boolean
): DiaCalendario => {
  const fechaTexto = formatearFecha(fecha);

  return {
    key: fechaTexto,
    fecha: fechaTexto,
    numero: fecha.getDate(),
    esMesActual,
    esHoy:
      fecha.toDateString() === fechaActual.toDateString()
  };
};

const formatearFecha = (fecha: Date) => {
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');

  return `${anio}-${mes}-${dia}`;
};

const seleccionarDia = (dia: DiaCalendario) => {
  fechaSeleccionada.value = dia.fecha;

  if (!dia.esMesActual) {
    mes.value = new Date(dia.fecha).getMonth();
    anio.value = new Date(dia.fecha).getFullYear();
  }
};

const mesAnterior = () => {
  if (mes.value === 0) {
    mes.value = 11;
    anio.value--;
  } else {
    mes.value--;
  }
};

const mesSiguiente = () => {
  if (mes.value === 11) {
    mes.value = 0;
    anio.value++;
  } else {
    mes.value++;
  }
};

const tieneEventos = (fecha: string) => {
  return eventos.value.some(evento => evento.fecha === fecha);
};

const agregarEvento = () => {
  const nuevoEvento: Evento = {
    id: Date.now(),
    fecha: fechaSeleccionada.value,
    titulo: 'Nuevo evento',
    hora: '12:00',
    color: '#6366f1'
  };

  eventos.value.push(nuevoEvento);
};
</script>

<style scoped>
.calendario {
  padding: 14px;
}

.dias-semana {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: var(--ion-color-medium);
  margin-bottom: 8px;
}

.dias {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.dia {
  position: relative;
  height: 46px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--ion-text-color);
  font-size: 14px;
}

.dia.otro-mes {
  color: var(--ion-color-medium);
  opacity: 0.45;
}

.dia.hoy {
  background: #6366f1;
  color: white;
  font-weight: bold;
}

.dia.seleccionado:not(.hoy) {
  background: rgba(99, 102, 241, 0.15);
  color: #6366f1;
  font-weight: 600;
}

.indicador-evento {
  position: absolute;
  bottom: 5px;
  left: 50%;
  transform: translateX(-50%);
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #6366f1;
}

.dia.hoy .indicador-evento {
  background: white;
}

.eventos {
  padding: 10px 16px 30px;
}

.eventos-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.eventos-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.color-evento {
  width: 6px;
  height: 42px;
  border-radius: 4px;
}

.sin-eventos {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: var(--ion-color-medium);
}

.sin-eventos ion-icon {
  font-size: 42px;
  margin-bottom: 10px;
}

.sin-eventos p {
  margin: 0;
}
</style>
