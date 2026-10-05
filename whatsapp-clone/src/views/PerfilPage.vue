<template>
  <ion-page class="perfil-page">
    <ion-header class="glass-header">
      <ion-toolbar> <ion-title>Perfil</ion-title> </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <div class="perfil-card">
          <div class="foto-container">
            <div class="foto-ring">
              <ion-avatar class="foto-perfil">
                <img v-if="usuario.avatar" :src="usuario.avatar" :alt="nombreCompleto" />
              </ion-avatar>
            </div>

            <ion-button
              class="boton-camara"
              shape="round"
              aria-label="Cambiar foto de perfil"
              :disabled="subiendoFoto"
              @click="seleccionarFoto"
            >
              <ion-icon :icon="cameraOutline" />
            </ion-button>
            <input
              ref="entradaFoto"
              class="entrada-foto-oculta"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="subirFotoPerfil"
            />
            <input
              ref="entradaFondo"
              class="entrada-foto-oculta"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="guardarFondoChats"
            />
          </div>

          <h1>{{ nombreCompleto }}</h1>
          <p class="telefono">{{ usuario.telefono }}</p>
          <p v-if="usuario.descripcion" class="descripcion-perfil">
            {{ usuario.descripcion }}
          </p>

          <ion-button
            fill="outline"
            shape="round"
            class="boton-editar"
            @click="editarPerfil"
          >
            <ion-icon slot="start" :icon="createOutline" />
            Editar perfil
          </ion-button>
        </div>

        <div class="seccion">
          <span class="seccion-titulo">CUENTA</span>

          <ion-list class="lista-opciones">
            <ion-item button lines="none" class="opcion" @click="editarNotificaciones">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="notificationsOutline" />
              </div>

              <ion-label>
                <h2>Notificaciones</h2>
                <p>{{ resumenNotificaciones }}</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion" @click="editarPrivacidad">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="lockClosedOutline" />
              </div>

              <ion-label>
                <h2>Privacidad</h2>
                <p>✓ Foto: {{ etiquetaPrivacidad(privacidad.foto_perfil) }} · Info: {{ etiquetaPrivacidad(privacidad.descripcion) }}</p>
                <p>✓ Historias: {{ etiquetaPrivacidad(privacidad.estados) }}</p>
                <p>✓ Últ. conexión: {{ etiquetaPrivacidad(privacidad.ultima_conexion) }}</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion" @click="cambiarColorApp">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="colorPaletteOutline" />
              </div>

              <ion-label>
                <h2>Apariencia</h2>
                <p>Tema y colores de la aplicación</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion" @click="cambiarFondoChats">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="imageOutline" />
              </div>

              <ion-label>
                <h2>Fondo de chats</h2>
                <p>Elige una imagen para tus conversaciones</p>
              </ion-label>

              <ion-icon slot="end" class="flecha" :icon="chevronForwardOutline" />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="cloudOutline" />
              </div>

              <ion-label>
                <h2>Almacenamiento</h2>
                <p>Archivos, imágenes y documentos</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="informationCircleOutline" />
              </div>

              <ion-label>
                <h2>Acerca de</h2>
                <p>Información de la aplicación</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>
          </ion-list>
        </div>

        <div class="seccion">
          <span class="seccion-titulo">SESIÓN</span>

          <ion-list class="lista-opciones">
            <ion-item
              button
              lines="none"
              class="opcion cerrar-sesion"
              @click="cerrarSesion"
            >
              <div slot="start" class="icono-opcion icono-salida">
                <ion-icon :icon="logOutOutline" />
              </div>

              <ion-label>
                <h2>Cerrar sesión</h2>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>
          </ion-list>
        </div>

        <div class="version">MiApp v1.0.0</div>
      </div>
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
  IonAvatar,
  IonButton,
  IonIcon,
  IonList,
  IonItem,
  IonLabel,
  alertController,
  actionSheetController,
  onIonViewWillEnter,
} from "@ionic/vue";
import {
  cameraOutline,
  createOutline,
  notificationsOutline,
  lockClosedOutline,
  colorPaletteOutline,
  cloudOutline,
  imageOutline,
  informationCircleOutline,
  logOutOutline,
  chevronForwardOutline,
} from "ionicons/icons";
import {
  APP_THEMES,
  guardarPreferenciasApp,
  guardarFondoEnCuenta,
  guardarTemaEnCuenta,
  quitarFondoDeCuenta,
} from "../utils/appPreferences";

interface UsuarioPerfil {
  id: number;
  telefono: string;
  nombre: string;
  apellido: string | null;
  foto: string | null;
  descripcion?: string | null;
}

interface PrivacidadPerfil {
  foto_perfil: "todos" | "contactos" | "nadie";
  descripcion: "todos" | "contactos" | "nadie";
  estados: "todos" | "contactos" | "nadie" | "contactos_excepto" | "solo_seleccionados";
  ultima_conexion: "todos" | "contactos" | "nadie";
  contactos_estados: number[];
}

interface PreferenciasNotificaciones {
  privados: boolean;
  grupos: boolean;
  estados: boolean;
}

const router = useRouter();
const entradaFoto = ref<HTMLInputElement | null>(null);
const entradaFondo = ref<HTMLInputElement | null>(null);
const subiendoFoto = ref(false);
const usuario = ref({
  id: 0,
  nombre: "",
  apellido: "",
  telefono: "",
  descripcion: "",
  avatar: "",
});
const privacidad = ref<PrivacidadPerfil>({
  foto_perfil: "contactos",
  descripcion: "contactos",
  estados: "contactos",
  ultima_conexion: "contactos",
  contactos_estados: [],
});
const preferenciasNotificaciones = ref<PreferenciasNotificaciones>({
  privados: true,
  grupos: true,
  estados: true,
});

const nombreCompleto = computed(
  () => `${usuario.value.nombre} ${usuario.value.apellido}`.trim(),
);
const resumenNotificaciones = computed(() => {
  const activos = [
    preferenciasNotificaciones.value.privados ? "Privados" : "",
    preferenciasNotificaciones.value.grupos ? "Grupos" : "",
    preferenciasNotificaciones.value.estados ? "Historias" : "",
  ].filter(Boolean);
  return activos.length ? `Activadas: ${activos.join(", ")}` : "Notificaciones desactivadas";
});

const etiquetaPrivacidad = (valor: PrivacidadPerfil["foto_perfil"] | PrivacidadPerfil["estados"]) => {
  const etiquetas: Record<string, string> = {
    todos: "Todos",
    contactos: "Mis contactos",
    nadie: "Nadie",
    contactos_excepto: "Contactos excepto seleccionados",
    solo_seleccionados: "Solo seleccionados",
  };
  return etiquetas[valor] || "Cargando…";
};

const mostrarAlerta = async (header: string, message: string) => {
  const alerta = await alertController.create({
    mode: "ios",
    header,
    message,
    buttons: ["Aceptar"],
  });
  await alerta.present();
};

const guardarUsuarioLocal = (usuarioApi: UsuarioPerfil) => {
  localStorage.setItem("usuario", JSON.stringify(usuarioApi));
  usuario.value = {
    id: Number(usuarioApi.id),
    nombre: usuarioApi.nombre || "",
    apellido: usuarioApi.apellido || "",
    telefono: usuarioApi.telefono || "",
    descripcion: usuarioApi.descripcion || "",
    avatar: usuarioApi.foto || "",
  };
};

const cargarUsuario = () => {
  const usuarioGuardado = localStorage.getItem("usuario");
  if (!usuarioGuardado) {
    return;
  }
  try {
    const usuarioApi = JSON.parse(usuarioGuardado) as UsuarioPerfil;
    guardarUsuarioLocal(usuarioApi);
  } catch (error) {
    console.error("No se pudieron leer los datos locales del perfil:", error);
  }
};

const cargarPrivacidad = async () => {
  const token = localStorage.getItem("token") || "";
  if (!token) return;

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=obtenerPrivacidad",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
        cache: "no-store",
      },
    );
    const resultado = await respuesta.json();
    if (resultado.ok && resultado.privacidad) privacidad.value = resultado.privacidad;
  } catch (error) {
    console.error("No se pudo cargar la privacidad:", error);
  }
};

const cargarPreferenciasNotificaciones = async () => {
  const token = localStorage.getItem("token") || "";
  if (!token) return;
  try {
    const datos = new URLSearchParams({ token });
    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/notificaciones_controlador.php?task=obtenerPreferencias",
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datos.toString(), cache: "no-store" },
    );
    const resultado = await respuesta.json();
    if (resultado.ok && resultado.preferencias) {
      preferenciasNotificaciones.value = resultado.preferencias;
    }
  } catch (error) {
    console.error("No se pudieron cargar las preferencias de notificaciones:", error);
  }
};

onIonViewWillEnter(() => {
  cargarUsuario();
  void cargarPrivacidad();
  void cargarPreferenciasNotificaciones();
});

const editarNotificaciones = async () => {
  const valoresActuales = preferenciasNotificaciones.value;
  const seleccionadas: string[] = [];
  const alerta = await alertController.create({
    mode: "ios",
    header: "Notificaciones",
    message: "Elige qué avisos quieres recibir.",
    inputs: [
      { type: "checkbox", label: "Mensajes privados", value: "privados", checked: valoresActuales.privados },
      { type: "checkbox", label: "Mensajes de grupos", value: "grupos", checked: valoresActuales.grupos },
      { type: "checkbox", label: "Nuevas historias", value: "estados", checked: valoresActuales.estados },
    ],
    buttons: [
      { text: "Cancelar", role: "cancel" },
      {
        text: "Guardar",
        role: "confirm",
        handler: async (valores: string[]) => {
          seleccionadas.splice(0, seleccionadas.length, ...(Array.isArray(valores) ? valores : []));
          if (
            seleccionadas.length > 0 &&
            "Notification" in window &&
            Notification.permission === "default"
          ) {
            await Notification.requestPermission();
          }
          return true;
        },
      },
    ],
  });
  await alerta.present();
  const { role } = await alerta.onDidDismiss();
  if (role !== "confirm") return;

  const token = localStorage.getItem("token") || "";
  if (!token) {
    await mostrarAlerta("Sesión no válida", "Inicia sesión para guardar tus notificaciones.");
    return;
  }
  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("privados", seleccionadas.includes("privados") ? "1" : "0");
    datos.append("grupos", seleccionadas.includes("grupos") ? "1" : "0");
    datos.append("estados", seleccionadas.includes("estados") ? "1" : "0");
    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/notificaciones_controlador.php?task=guardarPreferencias",
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datos.toString() },
    );
    const resultado = await respuesta.json();
    if (!respuesta.ok || !resultado.ok || !resultado.preferencias) {
      throw new Error(resultado.error || "No se pudieron guardar las preferencias.");
    }
    preferenciasNotificaciones.value = resultado.preferencias;
    await mostrarAlerta("Notificaciones guardadas", "Se aplicará a los mensajes e historias que lleguen desde ahora.");
  } catch (error) {
    console.error("No se pudieron guardar las preferencias de notificaciones:", error);
    await mostrarAlerta("No se pudo guardar", error instanceof Error ? error.message : "Inténtalo de nuevo.");
  }
};

const editarPrivacidad = async () => {
  const seccion = await actionSheetController.create({
    mode: "ios",
    header: "Ajustes de privacidad",
    buttons: [
      { text: "Foto de perfil", handler: () => void seleccionarVisibilidad("foto_perfil", "tu foto de perfil") },
      { text: "Descripción", handler: () => void seleccionarVisibilidad("descripcion", "tu descripción") },
      { text: "Última conexión", handler: () => void seleccionarVisibilidad("ultima_conexion", "tu última conexión") },
      { text: "Historias", handler: () => void seleccionarPrivacidadHistorias() },
      { text: "Cancelar", role: "cancel" },
    ],
  });
  await seccion.present();
};

const seleccionarVisibilidad = async (
  campo: "foto_perfil" | "descripcion" | "ultima_conexion",
  nombre: string,
) => {
  const opciones: Array<{ valor: "todos" | "contactos" | "nadie"; texto: string }> = [
    { valor: "todos", texto: "Todos" },
    { valor: "contactos", texto: "Mis contactos" },
    { valor: "nadie", texto: "Nadie" },
  ];
  const selector = await actionSheetController.create({
    mode: "ios",
    header: `Quién puede ver ${nombre}`,
    buttons: [
      ...opciones.map((opcion) => ({
        text: `${opcion.texto}${privacidad.value[campo] === opcion.valor ? " ✓" : ""}`,
        handler: async () => {
          const token = localStorage.getItem("token") || "";
          if (!token) {
            await mostrarAlerta("Sesión no válida", "Inicia sesión para guardar la privacidad.");
            return;
          }
          const nuevaPrivacidad = { ...privacidad.value, [campo]: opcion.valor };
          try {
            privacidad.value = await guardarPrivacidadEnServidor(token, nuevaPrivacidad);
            await mostrarAlerta("Privacidad actualizada", `Se guardó quién puede ver ${nombre}.`);
          } catch (error) {
            console.error("No se pudo guardar la privacidad:", error);
            await mostrarAlerta(
              "No se pudo guardar",
              error instanceof Error ? error.message : "Inténtalo de nuevo.",
            );
          }
        },
      })),
      { text: "Cancelar", role: "cancel" },
    ],
  });
  await selector.present();
};

const guardarPrivacidadEnServidor = async (token: string, ajustes: PrivacidadPerfil) => {
  const datos = new URLSearchParams();
  datos.append("token", token);
  datos.append("foto_perfil", ajustes.foto_perfil);
  datos.append("descripcion", ajustes.descripcion);
  datos.append("estados", ajustes.estados);
  datos.append("ultima_conexion", ajustes.ultima_conexion);
  datos.append("contactos_estados", JSON.stringify(ajustes.contactos_estados || []));

  const respuesta = await fetch(
    "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=guardarPrivacidad",
    {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: datos.toString(),
    },
  );
  const resultado = await respuesta.json();
  if (!respuesta.ok || !resultado.ok || !resultado.privacidad) {
    throw new Error(resultado.error || "No se pudo guardar la privacidad.");
  }
  return resultado.privacidad as PrivacidadPerfil;
};

const seleccionarPrivacidadHistorias = async () => {
  const valoresSimples = [
    { id: "contactos", label: "Mis contactos" },
  ] as const;
  const selector = await actionSheetController.create({
    mode: "ios",
    header: "Privacidad de historias nuevas",
    buttons: [
      ...valoresSimples.map((opcion) => ({
        text: `${opcion.label}${privacidad.value.estados === opcion.id ? " ✓" : ""}`,
        handler: () => void guardarAudienciaHistorias(opcion.id, []),
      })),
      {
        text: `Mis contactos excepto…${privacidad.value.estados === "contactos_excepto" ? " ✓" : ""}`,
        handler: () => void elegirContactosHistorias("contactos_excepto"),
      },
      {
        text: `Solo compartir con…${privacidad.value.estados === "solo_seleccionados" ? " ✓" : ""}`,
        handler: () => void elegirContactosHistorias("solo_seleccionados"),
      },
      { text: "Cancelar", role: "cancel" },
    ],
  });
  await selector.present();
};

const elegirContactosHistorias = async (
  modo: "contactos_excepto" | "solo_seleccionados",
) => {
  const token = localStorage.getItem("token") || "";
  if (!token) {
    await mostrarAlerta("Sesión no válida", "Inicia sesión para configurar la audiencia.");
    return;
  }

  try {
    const formulario = new URLSearchParams();
    formulario.append("token", token);
    const respuestaContactos = await fetch(
      "http://localhost/new%20app/api/controladores/contactos_controlador.php?task=obtenerContactos",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formulario.toString(),
        cache: "no-store",
      },
    );
    const resultadoContactos = await respuestaContactos.json();
    if (!resultadoContactos.ok) throw new Error(resultadoContactos.error || "No se pudieron cargar tus contactos.");

    const contactos = (resultadoContactos.contactos || []) as Array<any>;
    let seleccionados: number[] = [];
    const opciones = contactos.map((contacto) => {
      const id = Number(contacto.contacto_id);
      const nombre = `${contacto.nombre_personalizado || contacto.nombre || ""} ${contacto.apellido || ""}`.trim();
      const marcado = privacidad.value.contactos_estados.includes(id);
      return {
        type: "checkbox" as const,
        label: nombre || contacto.telefono || `Contacto ${id}`,
        value: id,
        checked: marcado,
      };
    });

    const alerta = await alertController.create({
      mode: "ios",
      header: modo === "contactos_excepto" ? "Excluir contactos" : "Compartir solo con",
      message: "Selecciona los contactos para tus historias nuevas.",
      inputs: opciones,
      buttons: [
        { text: "Cancelar", role: "cancel" },
        {
          text: "Guardar",
          role: "confirm",
          handler: (valores: number[]) => {
            seleccionados = Array.isArray(valores) ? valores.map(Number) : [];
            return true;
          },
        },
      ],
    });
    await alerta.present();
    const { role } = await alerta.onDidDismiss();
    if (role === "confirm") await guardarAudienciaHistorias(modo, seleccionados);
  } catch (error) {
    console.error("No se pudo configurar la audiencia de historias:", error);
    await mostrarAlerta(
      "No se pudo cargar la lista",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};

const guardarAudienciaHistorias = async (
  modo: PrivacidadPerfil["estados"],
  contactos: number[],
) => {
  const token = localStorage.getItem("token") || "";
  if (!token) {
    await mostrarAlerta("Sesión no válida", "Inicia sesión para guardar la audiencia.");
    return;
  }
  try {
    privacidad.value = await guardarPrivacidadEnServidor(token, {
      ...privacidad.value,
      estados: modo,
      contactos_estados: contactos,
    });
    await mostrarAlerta("Privacidad actualizada", "La audiencia se aplicará a las historias que publiques desde ahora.");
  } catch (error) {
    console.error("No se pudo guardar la audiencia:", error);
    await mostrarAlerta(
      "No se pudo guardar",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};

const editarPerfil = async () => {
  const token = localStorage.getItem("token") || "";
  if (!token) {
    await mostrarAlerta("Sesión no válida", "Inicia sesión para editar tu perfil.");
    return;
  }

  let nombre = usuario.value.nombre;
  let apellido = usuario.value.apellido;
  let descripcion = usuario.value.descripcion;
  const formulario = await alertController.create({
    mode: "ios",
    header: "Editar perfil",
    inputs: [
      { name: "nombre", type: "text", value: nombre, placeholder: "Nombre", attributes: { maxlength: 100 } },
      { name: "apellido", type: "text", value: apellido, placeholder: "Apellido", attributes: { maxlength: 100 } },
      { name: "descripcion", type: "text", value: descripcion, placeholder: "Acerca de ti", attributes: { maxlength: 255 } },
    ],
    buttons: [
      { text: "Cancelar", role: "cancel" },
      {
        text: "Guardar",
        role: "confirm",
        handler: (valores: any) => {
          nombre = String(valores?.nombre || "").trim();
          apellido = String(valores?.apellido || "").trim();
          descripcion = String(valores?.descripcion || "").trim();
          return true;
        },
      },
    ],
  });
  await formulario.present();
  const { role } = await formulario.onDidDismiss();
  if (role !== "confirm") return;

  if (!nombre) {
    await mostrarAlerta("Falta tu nombre", "El nombre no puede quedar vacío.");
    return;
  }

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("nombre", nombre);
    datos.append("apellido", apellido);
    datos.append("descripcion", descripcion);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=actualizarPerfil",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      },
    );
    const resultado = await respuesta.json();
    if (!resultado.ok || !resultado.usuario) {
      throw new Error(resultado.error || "No se pudo guardar el perfil.");
    }

    guardarUsuarioLocal(resultado.usuario as UsuarioPerfil);
    await mostrarAlerta("Perfil actualizado", "Tus datos se guardaron correctamente.");
  } catch (error) {
    console.error("Error al actualizar perfil:", error);
    await mostrarAlerta(
      "No se pudo actualizar",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  }
};

const seleccionarFoto = () => entradaFoto.value?.click();

const subirFotoPerfil = async (evento: Event) => {
  const elemento = evento.target as HTMLInputElement;
  const archivo = elemento.files?.[0];
  if (!archivo) return;

  if (archivo.size > 5 * 1024 * 1024) {
    await mostrarAlerta("Imagen muy grande", "La foto debe pesar menos de 5 MB.");
    elemento.value = "";
    return;
  }

  const token = localStorage.getItem("token") || "";
  if (!token) {
    await mostrarAlerta("Sesión no válida", "Inicia sesión para cambiar tu foto.");
    elemento.value = "";
    return;
  }

  subiendoFoto.value = true;
  try {
    const datos = new FormData();
    datos.append("token", token);
    datos.append("foto", archivo);

    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=actualizarFotoPerfil",
      { method: "POST", body: datos },
    );
    const resultado = await respuesta.json();
    if (!resultado.ok || !resultado.foto) {
      throw new Error(resultado.error || "No se pudo actualizar la foto.");
    }

    const usuarioGuardado = JSON.parse(localStorage.getItem("usuario") || "{}");
    usuarioGuardado.foto = resultado.foto;
    guardarUsuarioLocal(usuarioGuardado as UsuarioPerfil);
    await mostrarAlerta("Foto actualizada", "Tu foto de perfil se guardó correctamente.");
  } catch (error) {
    console.error("Error al subir la foto de perfil:", error);
    await mostrarAlerta(
      "No se pudo cambiar la foto",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  } finally {
    subiendoFoto.value = false;
    elemento.value = "";
  }
};

const cambiarColorApp = async () => {
  const selector = await actionSheetController.create({
    mode: "ios",
    header: "Color de la aplicación",
    buttons: [
      ...APP_THEMES.map((tema) => ({
        text: tema.label,
        handler: async () => {
          const token = localStorage.getItem("token") || "";
          if (!token) {
            await mostrarAlerta("Sesión no válida", "Inicia sesión para guardar tu apariencia.");
            return;
          }
          try {
            const preferencias = await guardarTemaEnCuenta(token, tema.id);
            guardarPreferenciasApp(preferencias);
          } catch (error) {
            console.error("No se pudo guardar el tema:", error);
            await mostrarAlerta(
              "No se pudo guardar el color",
              error instanceof Error ? error.message : "Inténtalo de nuevo.",
            );
          }
        },
      })),
      { text: "Cancelar", role: "cancel" as const },
    ],
  });
  await selector.present();
};

const cambiarFondoChats = async () => {
  const selector = await actionSheetController.create({
    mode: "ios",
    header: "Fondo de chats",
    buttons: [
      {
        text: "Elegir una foto",
        handler: () => entradaFondo.value?.click(),
      },
      {
        text: "Restaurar fondo original",
        handler: async () => {
          try {
            const token = localStorage.getItem("token") || "";
            if (!token) throw new Error("Inicia sesión para guardar tus preferencias.");
            const preferencias = await quitarFondoDeCuenta(token);
            guardarPreferenciasApp(preferencias);
            await mostrarAlerta("Fondo restaurado", "Se quitó la imagen de fondo de tus chats.");
          } catch (error) {
            console.error("No se pudo restaurar el fondo:", error);
            await mostrarAlerta("No se pudo restaurar", "Inténtalo de nuevo.");
          }
        },
      },
      { text: "Cancelar", role: "cancel" },
    ],
  });
  await selector.present();
};

const leerArchivoComoDataUrl = (archivo: File) =>
  new Promise<string>((resolve, reject) => {
    const lector = new FileReader();
    lector.onload = () =>
      typeof lector.result === "string" ? resolve(lector.result) : reject(new Error("No se pudo leer la foto."));
    lector.onerror = () => reject(new Error("No se pudo leer la foto."));
    lector.readAsDataURL(archivo);
  });

const comprimirFondo = async (archivo: File) => {
  const origen = await leerArchivoComoDataUrl(archivo);
  const imagen = await new Promise<HTMLImageElement>((resolve, reject) => {
    const elemento = new Image();
    elemento.onload = () => resolve(elemento);
    elemento.onerror = () => reject(new Error("El archivo no es una imagen válida."));
    elemento.src = origen;
  });

  const escala = Math.min(1, 1200 / Math.max(imagen.naturalWidth, imagen.naturalHeight));
  const canvas = document.createElement("canvas");
  canvas.width = Math.max(1, Math.round(imagen.naturalWidth * escala));
  canvas.height = Math.max(1, Math.round(imagen.naturalHeight * escala));
  const contexto = canvas.getContext("2d");
  if (!contexto) throw new Error("No se pudo procesar la imagen.");

  contexto.drawImage(imagen, 0, 0, canvas.width, canvas.height);
  const comprimida = canvas.toDataURL("image/jpeg", 0.66);
  if (comprimida.length > 1_500_000) {
    throw new Error("La imagen sigue siendo muy pesada. Elige otra foto más pequeña.");
  }
  return comprimida;
};

const guardarFondoChats = async (evento: Event) => {
  const elemento = evento.target as HTMLInputElement;
  const archivo = elemento.files?.[0];
  if (!archivo) return;

  if (!archivo.type.startsWith("image/") || archivo.size > 5 * 1024 * 1024) {
    await mostrarAlerta("Imagen no válida", "Elige una imagen de hasta 5 MB.");
    elemento.value = "";
    return;
  }

  try {
    const wallpaperDataUrl = await comprimirFondo(archivo);
    const token = localStorage.getItem("token") || "";
    if (!token) throw new Error("Inicia sesión para guardar tus preferencias.");
    const fondo = await fetch(wallpaperDataUrl).then((respuesta) => respuesta.blob());
    const preferencias = await guardarFondoEnCuenta(token, fondo);
    guardarPreferenciasApp(preferencias);
    await mostrarAlerta("Fondo actualizado", "Se guardó en tu cuenta y se aplicará en tus dispositivos.");
  } catch (error) {
    console.error("No se pudo guardar el fondo de chats:", error);
    await mostrarAlerta(
      "No se pudo guardar el fondo",
      error instanceof Error ? error.message : "Inténtalo de nuevo.",
    );
  } finally {
    elemento.value = "";
  }
};

const cerrarSesion = async () => {
  const confirmacion = await alertController.create({
    mode: "ios",
    header: "Cerrar sesión",
    message: "¿Quieres salir de tu cuenta en este dispositivo?",
    buttons: [
      { text: "Cancelar", role: "cancel" },
      { text: "Cerrar sesión", role: "destructive" },
    ],
  });
  await confirmacion.present();
  const { role } = await confirmacion.onDidDismiss();
  if (role !== "destructive") return;

  const token = localStorage.getItem("token") || "";
  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    await fetch(
      "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=cerrarSesion",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      },
    );
  } catch (error) {
    console.error("No se pudo notificar el cierre de sesión al servidor:", error);
  } finally {
    localStorage.removeItem("token");
    localStorage.removeItem("usuario");
    await router.replace("/login");
  }
};
</script>
<style scoped>
.perfil-page {
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
  font-size: 24px;
  font-weight: 700;
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
  padding: 10px 14px 100px;
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
  top: 50px;
  left: -140px;
  background: var(--app-accent, #10b981);
}
.glow-cyan {
  top: 400px;
  right: -150px;
  background: var(--app-accent-secondary, #06b6d4);
}
.perfil-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 26px 20px 24px;
  text-align: center;
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 24px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
}
.foto-container {
  position: relative;
  margin-bottom: 16px;
}
.entrada-foto-oculta {
  display: none;
}
.foto-ring {
  width: 116px;
  height: 116px;
  padding: 3px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow:
    0 0 25px rgba(16, 185, 129, 0.2),
    0 0 45px rgba(6, 182, 212, 0.08);
}
.foto-perfil {
  width: 110px;
  height: 110px;
  background: #090d16;
}
.foto-perfil img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid #090d16;
}
.boton-camara {
  position: absolute;
  right: -4px;
  bottom: -3px;
  width: 42px;
  height: 42px;
  margin: 0;
  --background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  --color: #ffffff;
  --box-shadow: 0 0 16px rgba(16, 185, 129, 0.28);
}
.boton-camara ion-icon {
  font-size: 20px;
}
.perfil-card h1 {
  margin: 4px 0 5px;
  color: #ffffff;
  font-size: 24px;
  font-weight: 700;
}
.telefono {
  margin: 0 0 17px;
  color: #8993a5;
  font-size: 14px;
}
.descripcion-perfil {
  max-width: 280px;
  margin: -8px 0 17px;
  color: #b3bdca;
  font-size: 13px;
  line-height: 1.45;
}
.boton-editar {
  --border-color: rgba(16, 185, 129, 0.45);
  --color: var(--app-accent, #10b981);
  --background: rgba(16, 185, 129, 0.06);
  --background-hover: rgba(16, 185, 129, 0.12);
  --border-radius: 18px;
  font-weight: 600;
  text-transform: none;
}
.seccion {
  margin-top: 24px;
}
.seccion-titulo {
  display: block;
  margin: 0 5px 10px;
  color: var(--app-accent, #10b981);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.2px;
}
.lista-opciones {
  margin: 0;
  padding: 0;
  background: transparent;
}
.opcion {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 72px;
  margin-bottom: 8px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 18px;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}
.icono-opcion {
  width: 40px;
  height: 40px;
  margin-right: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 13px;
  background: rgba(16, 185, 129, 0.09);
  border: 1px solid rgba(16, 185, 129, 0.14);
  color: var(--app-accent, #10b981);
}
.icono-opcion ion-icon {
  font-size: 21px;
}
.opcion ion-label h2 {
  margin: 0 0 4px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}
.opcion ion-label p {
  margin: 0;
  color: #8993a5;
  font-size: 12px;
}
.flecha {
  color: #596273;
  font-size: 18px;
}
.cerrar-sesion {
  --background: rgba(239, 68, 68, 0.045);
  border-color: rgba(239, 68, 68, 0.08);
}
.cerrar-sesion .icono-opcion {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}
.cerrar-sesion ion-label h2 {
  color: #ef4444;
}
.cerrar-sesion .flecha {
  color: rgba(239, 68, 68, 0.55);
}
.version {
  text-align: center;
  padding: 20px 0 20px;
  color: #596273;
  font-size: 11px;
}
</style>
