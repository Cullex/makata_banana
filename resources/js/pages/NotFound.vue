<template>
    <Empty icon="banana" title="Page not found" text="This aisle of the warehouse doesn't exist.">
        <RouterLink to="/" class="btn-primary">Back to dashboard</RouterLink>
    </Empty>
</template>
<script setup>
import Empty from '../components/Empty.vue';
</script>
