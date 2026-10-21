const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || "http://localhost/new%20app/api").replace(/\/+$/, "");

export const API_URL = `${API_BASE_URL}/controladores/`;

export default API_BASE_URL;
