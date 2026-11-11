<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '../types';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const flash = computed(() => page.props.flash);
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <Link href="/" class="inline-flex items-center gap-2">
                <span class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-xl shadow-md shadow-blue-500/20">
                    J
                </span>
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ appName }}</span>
            </Link>
            <p class="mt-2 text-sm text-slate-600">
                <slot name="subtitle">Pemesanan Tiket Museum Online</slot>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
            <!-- Flash Message Alerts -->
            <div v-if="flash.success" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                {{ flash.error }}
            </div>
            <div v-if="flash.status" class="mb-4 rounded-xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                {{ flash.status }}
            </div>

            <div class="bg-white py-8 px-6 shadow-sm border border-slate-200/80 rounded-2xl sm:px-10">
                <slot />
            </div>

            <div class="mt-8 text-center text-xs text-slate-500">
                &copy; {{ new Date().getFullYear() }} {{ appName }}. Hak cipta dilindungi.
            </div>
        </div>
    </div>
</template>
