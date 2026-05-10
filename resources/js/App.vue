<script setup>
// Shell raiz da SPA. As páginas reais são montadas via <RouterView />
// em T016+; aqui ficamos no mínimo necessário para o entrypoint
// compilar e o smoke test E2E (T138+) carregar a aplicação.
</script>

<template>
  <RouterView />
</template>
