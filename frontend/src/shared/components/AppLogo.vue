<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BrandLogo from '@shared/components/BrandLogo.vue';

const name = (usePage().props.name as string | undefined) || 'XHopy';
</script>

<template>
    <BrandLogo :name="name" size="sm" class="truncate" />
</template>
