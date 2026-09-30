<template> <ion-page> <ion-header> <ion-toolbar> <ion-buttons slot="start"> <ion-button class="boton-cerrar" @click="cerrarHistorias"> <ion-icon :icon="closeOutline" /> </ion-button> </ion-buttons>
    <ion-avatar class="avatar-header">
      <img :src="historia.avatar" />
    </ion-avatar>

    <ion-title>{{ historia.nombre }}</ion-title>
  </ion-toolbar>
</ion-header>

<ion-content :fullscreen="true" class="historia-content">
  <div class="historia-container">

    <div class="progreso-historias">
      <div
        v-for="item in historias"
        :key="item.id"
        class="progreso"
      >
        <div
          class="progreso-activo"
          :class="{ completado: item.id < historia.id }"
          :style="item.id === historia.id ? { width: progreso + '%' } : {}"
        ></div>
      </div>
    </div>

    <div
      class="zona-historia"
      @click="alternarPausa"
    >
      <img
        :src="historia.imagen"
        :alt="historia.nombre"
        class="historia-imagen"
      />
    </div>

    <button class="boton-anterior" @click="historiaAnterior">
      <ion-icon :icon="chevronBackOutline" />
    </button>

    <button class="boton-siguiente" @click="siguienteHistoria">
      <ion-icon :icon="chevronForwardOutline" />
    </button>

  </div>
</ion-content>
</ion-page> </template> <script setup lang="ts"> import { computed, onMounted, onUnmounted, ref, watch } from 'vue'; import { useRoute, useRouter } from 'vue-router'; import { IonPage, IonHeader, IonToolbar, IonButtons, IonButton, IonIcon, IonAvatar, IonTitle, IonContent } from '@ionic/vue'; import { closeOutline, chevronBackOutline, chevronForwardOutline } from 'ionicons/icons'; const route = useRoute(); const router = useRouter(); const historias = [ { id: 1, nombre: 'Juan Pérez', avatar: 'https://i.pravatar.cc/150?img=12', imagen: 'https://picsum.photos/id/1011/900/1600' }, { id: 2, nombre: 'María López', avatar: 'https://i.pravatar.cc/150?img=5', imagen: 'https://picsum.photos/id/1015/900/1600' }, { id: 3, nombre: 'Carlos Ramírez', avatar: 'https://i.pravatar.cc/150?img=3', imagen: 'https://picsum.photos/id/1016/900/1600' }, { id: 4, nombre: 'Ana Martínez', avatar: 'https://i.pravatar.cc/150?img=9', imagen: 'https://picsum.photos/id/1025/900/1600' }, { id: 5, nombre: 'Luis Hernández', avatar: 'https://i.pravatar.cc/150?img=8', imagen: 'https://picsum.photos/id/1035/900/1600' }, { id: 6, nombre: 'Pedro Sánchez', avatar: 'https://i.pravatar.cc/150?img=14', imagen: 'https://picsum.photos/id/1040/900/1600' } ]; const historia = computed(() => { const id = Number(route.params.id); return historias.find(item => item.id === id) || historias[0]; }); const progreso = ref(0); const pausada = ref(false); let intervalo: ReturnType<typeof setInterval> | null = null; const alternarPausa = () => { pausada.value = !pausada.value; }; const iniciarProgreso = () => { if (intervalo) { clearInterval(intervalo); } progreso.value = 0; pausada.value = false; intervalo = setInterval(() => { if (pausada.value) { return; } progreso.value += 1; if (progreso.value >= 100) { detenerProgreso(); siguienteHistoria(); } }, 50); }; const detenerProgreso = () => { if (intervalo) { clearInterval(intervalo); intervalo = null; } }; const cerrarHistorias = () => { detenerProgreso(); router.push('/historias'); }; const siguienteHistoria = () => { const indiceActual = historias.findIndex( item => item.id === historia.value.id ); const siguienteIndice = indiceActual === historias.length - 1 ? 0 : indiceActual + 1; router.push(`/historia/${historias[siguienteIndice].id}`); }; const historiaAnterior = () => { const indiceActual = historias.findIndex( item => item.id === historia.value.id ); const anteriorIndice = indiceActual === 0 ? historias.length - 1 : indiceActual - 1; router.push(`/historia/${historias[anteriorIndice].id}`); }; watch( () => historia.value.id, () => { iniciarProgreso(); } ); onMounted(() => { iniciarProgreso(); }); onUnmounted(() => { detenerProgreso(); }); </script> <style scoped> .historia-content { --background: #000; } .historia-container { position: relative; width: 100%; height: 100%; background: #000; display: flex; align-items: center; justify-content: center; } .zona-historia { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; } .historia-imagen { width: 100%; height: 100%; object-fit: contain; } .progreso-historias { position: absolute; top: 10px; left: 10px; right: 10px; z-index: 10; display: flex; gap: 4px; } .progreso { flex: 1; height: 4px; background: rgba(255, 255, 255, 0.35); border-radius: 10px; overflow: hidden; } .progreso-activo { width: 0; height: 100%; background: white; border-radius: 10px; } .progreso-activo.completado { width: 100%; } .avatar-header { width: 36px; height: 36px; margin-left: 8px; margin-right: 8px; } .avatar-header img { width: 100%; height: 100%; object-fit: cover; } .boton-anterior, .boton-siguiente { position: absolute; top: 50%; transform: translateY(-50%); width: 48px; height: 48px; border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 50%; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(6px); color: white; display: flex; align-items: center; justify-content: center; font-size: 26px; cursor: pointer; transition: all 0.2s ease; } .boton-anterior { left: 18px; } .boton-siguiente { right: 18px; } .boton-anterior:hover, .boton-siguiente:hover { background: rgba(255, 255, 255, 0.18); transform: translateY(-50%) scale(1.08); } .boton-anterior:active, .boton-siguiente:active { transform: translateY(-50%) scale(0.92); } .boton-anterior ion-icon, .boton-siguiente ion-icon { font-size: 28px; } .boton-cerrar { --color: white; --padding-start: 12px; --padding-end: 12px; --padding-top: 12px; --padding-bottom: 12px; } .boton-cerrar ion-icon { font-size: 32px; } </style>