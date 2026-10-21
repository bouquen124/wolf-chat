export interface AppPreferences {
  themeId: string;
  wallpaperUrl: string | null;
}

export const APP_THEMES = [
  { id: "verde", label: "Verde y turquesa", primary: "#10b981", secondary: "#06b6d4" },
  { id: "azul", label: "Azul y celeste", primary: "#3b82f6", secondary: "#06b6d4" },
  { id: "morado", label: "Morado y rosa", primary: "#8b5cf6", secondary: "#ec4899" },
  { id: "naranja", label: "Naranja y rojo", primary: "#f97316", secondary: "#ef4444" },
  { id: "rosa", label: "Rosa y violeta", primary: "#ec4899", secondary: "#8b5cf6" },
] as const;

const STORAGE_KEY = "preferenciasApp";
const TEMA_PREDETERMINADO = APP_THEMES[0];
const API = "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/usuarios_controlador.php";

export const leerPreferenciasApp = (): AppPreferences => {
  try {
    const guardado = localStorage.getItem(STORAGE_KEY);
    if (!guardado) return { themeId: TEMA_PREDETERMINADO.id, wallpaperUrl: null };

    const parseado = JSON.parse(guardado) as Partial<AppPreferences> & { wallpaperDataUrl?: string | null };
    const existeTema = APP_THEMES.some((tema) => tema.id === parseado.themeId);
    const fondo = parseado.wallpaperUrl ?? parseado.wallpaperDataUrl ?? null;
    const fondoValido =
      typeof fondo === "string" &&
      (fondo.startsWith("data:image/") || fondo.startsWith("/new%20app/api/uploads/fondos_chat/"))
        ? fondo
        : null;

    return {
      themeId: existeTema ? (parseado.themeId as string) : TEMA_PREDETERMINADO.id,
      wallpaperUrl: fondoValido,
    };
  } catch {
    return { themeId: TEMA_PREDETERMINADO.id, wallpaperUrl: null };
  }
};

export const guardarPreferenciasApp = (preferencias: AppPreferences) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(preferencias));
  aplicarPreferenciasApp(preferencias);
};

export const aplicarPreferenciasApp = (
  preferencias: AppPreferences = leerPreferenciasApp(),
) => {
  if (typeof document === "undefined") return;

  const tema = APP_THEMES.find((item) => item.id === preferencias.themeId) ?? TEMA_PREDETERMINADO;
  const raiz = document.documentElement;
  raiz.style.setProperty("--app-accent", tema.primary);
  raiz.style.setProperty("--app-accent-secondary", tema.secondary);

  if (preferencias.wallpaperUrl) {
    const fondo = `linear-gradient(rgba(9, 13, 22, 0.58), rgba(9, 13, 22, 0.58)), url("${preferencias.wallpaperUrl}")`;
    raiz.style.setProperty("--app-chat-wallpaper", fondo);
  } else {
    raiz.style.removeProperty("--app-chat-wallpaper");
  }
};

const postFormulario = async (task: string, form: URLSearchParams | FormData) => {
  const respuesta = await fetch(`${API}?task=${task}`, { method: "POST", body: form });
  const datos = await respuesta.json();
  if (!respuesta.ok || !datos.ok) throw new Error(datos.error || "No se pudieron guardar las preferencias.");
  return datos;
};

export const guardarTemaEnCuenta = async (token: string, themeId: string) => {
  const form = new URLSearchParams();
  form.append("token", token);
  form.append("theme_id", themeId);
  const datos = await postFormulario("guardarTema", form);
  return datos.preferencias as AppPreferences;
};

export const guardarFondoEnCuenta = async (token: string, fondo: File | Blob) => {
  const form = new FormData();
  form.append("token", token);
  form.append("fondo", fondo, fondo instanceof File ? fondo.name : "fondo.jpg");
  const datos = await postFormulario("actualizarFondoChat", form);
  return datos.preferencias as AppPreferences;
};

export const quitarFondoDeCuenta = async (token: string) => {
  const form = new URLSearchParams();
  form.append("token", token);
  const datos = await postFormulario("eliminarFondoChat", form);
  return datos.preferencias as AppPreferences;
};

export const sincronizarPreferenciasCuenta = async (token: string) => {
  const form = new URLSearchParams();
  form.append("token", token);
  const datos = await postFormulario("obtenerPreferencias", form);
  return {
    preferencias: datos.preferencias as AppPreferences,
    configurada: Boolean(datos.configurada),
  };
};
