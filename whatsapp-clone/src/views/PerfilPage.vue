<template> <ion-page> <ion-header> <ion-toolbar> <ion-title>Perfil</ion-title> </ion-toolbar> </ion-header>
<ion-content>
  <div class="perfil">

    <div class="foto-container">
      <ion-avatar class="foto-perfil">
        <img :src="usuario.avatar" />
      </ion-avatar>

      <ion-button
        class="boton-camara"
        shape="round"
      >
        <ion-icon :icon="cameraOutline" />
      </ion-button>
    </div>

    <h1>{{ usuario.nombre }}</h1>
    <p class="telefono">{{ usuario.telefono }}</p>

    <ion-button
      fill="outline"
      shape="round"
      class="boton-editar"
    >
      <ion-icon
        slot="start"
        :icon="createOutline"
      />
      Editar perfil
    </ion-button>

  </div>

  <ion-list inset>

    <ion-item button>
      <ion-icon
        slot="start"
        :icon="notificationsOutline"
      />

      <ion-label>
        <h2>Notificaciones</h2>
        <p>Sonidos, alertas y mensajes</p>
      </ion-label>

      <ion-icon
        slot="end"
        :icon="chevronForwardOutline"
      />
    </ion-item>

    <ion-item button>
      <ion-icon
        slot="start"
        :icon="lockClosedOutline"
      />

      <ion-label>
        <h2>Privacidad</h2>
        <p>Controla quién puede contactarte</p>
      </ion-label>

      <ion-icon
        slot="end"
        :icon="chevronForwardOutline"
      />
    </ion-item>

    <ion-item button>
      <ion-icon
        slot="start"
        :icon="colorPaletteOutline"
      />

      <ion-label>
        <h2>Apariencia</h2>
        <p>Tema y colores de la aplicación</p>
      </ion-label>

      <ion-icon
        slot="end"
        :icon="chevronForwardOutline"
      />
    </ion-item>

    <ion-item button>
      <ion-icon
        slot="start"
        :icon="cloudOutline"
      />

      <ion-label>
        <h2>Almacenamiento</h2>
        <p>Archivos, imágenes y documentos</p>
      </ion-label>

      <ion-icon
        slot="end"
        :icon="chevronForwardOutline"
      />
    </ion-item>

    <ion-item button>
      <ion-icon
        slot="start"
        :icon="informationCircleOutline"
      />

      <ion-label>
        <h2>Acerca de</h2>
        <p>Información de la aplicación</p>
      </ion-label>

      <ion-icon
        slot="end"
        :icon="chevronForwardOutline"
      />
    </ion-item>

  </ion-list>

  <ion-list inset>

    <ion-item
      button
      lines="none"
      class="cerrar-sesion"
    >
      <ion-icon
        slot="start"
        :icon="logOutOutline"
      />

      <ion-label>
        Cerrar sesión
      </ion-label>
    </ion-item>

  </ion-list>

  <div class="version">
    MiApp v1.0.0
  </div>

</ion-content>
</ion-page> </template> <script setup lang="ts"> import { onMounted, ref } from 'vue'; import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonAvatar, IonButton, IonIcon, IonList, IonItem, IonLabel } from '@ionic/vue'; import { cameraOutline, createOutline, notificationsOutline, lockClosedOutline, colorPaletteOutline, cloudOutline, informationCircleOutline, logOutOutline, chevronForwardOutline } from 'ionicons/icons'; interface Usuario { id: number; telefono: string; nombre: string; apellido: string; foto: string | null; } const usuario = ref({ nombre: '', telefono: '', avatar: 'https://i.pravatar.cc/300?img=12' }); const cargarUsuario = () => { const usuarioGuardado = localStorage.getItem('usuario'); if (!usuarioGuardado) { return; } const usuarioApi: Usuario = JSON.parse(usuarioGuardado); usuario.value = { nombre: `${usuarioApi.nombre} ${usuarioApi.apellido}`, telefono: usuarioApi.telefono, avatar: usuarioApi.foto || 'https://i.pravatar.cc/300?img=12' }; }; onMounted(() => { cargarUsuario(); }); </script> <style scoped> .perfil { display: flex; flex-direction: column; align-items: center; padding: 30px 20px 24px; text-align: center; } .foto-container { position: relative; margin-bottom: 14px; } .foto-perfil { width: 110px; height: 110px; } .foto-perfil img { width: 100%; height: 100%; object-fit: cover; } .boton-camara { position: absolute; right: -4px; bottom: -4px; width: 42px; height: 42px; margin: 0; --background: #6366f1; --color: white; } .perfil h1 { margin: 4px 0; font-size: 24px; font-weight: 700; color: var(--ion-text-color); } .telefono { margin: 0 0 16px; color: var(--ion-color-medium); font-size: 14px; } .boton-editar { --border-color: #6366f1; --color: #6366f1; } ion-list { margin-bottom: 16px; } ion-item ion-icon[slot="start"] { color: #6366f1; font-size: 22px; } .cerrar-sesion ion-icon { color: #ef4444 !important; } .cerrar-sesion ion-label { color: #ef4444; font-weight: 600; } .version { text-align: center; padding: 10px 0 30px; color: var(--ion-color-medium); font-size: 12px; } </style>