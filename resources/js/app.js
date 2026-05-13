import { createApp } from "vue";
import { createPinia } from "pinia";

import router from "./router";
import i18n from "./i18n";
import App from "./App.vue";

import "./echo";

import { useAuthStore } from "@/stores/auth.js";

const app = createApp(App);

const pinia = createPinia();
app.use(pinia);
app.use(router);
app.use(i18n);

// Boot auth ANTES do mount: rehidrata token de localStorage + valida via /auth/me.
// Se o token estiver inválido/expirado, clearToken() é chamado silenciosamente.
// O app monta deslogado e o router guard redireciona para /login se necessário.
const authStore = useAuthStore();
authStore.boot().finally(() => {
    app.mount("#app");
});
