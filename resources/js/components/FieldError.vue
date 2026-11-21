<template>
    <p v-if="msg" class="text-xs text-red-600 mt-1">{{ Array.isArray(msg) ? msg[0] : msg }}</p>
</template>
<script setup>
defineProps({ msg: [String, Array] });
</script>
