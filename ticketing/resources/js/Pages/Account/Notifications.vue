<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
interface NotificationRow {
    id: string;
    type: string;
    title: string;
    body: string | null;
    read_at: string | null;
    created_at: string;
}

const props = defineProps<{
    notifications: Paginated<NotificationRow>;
    unreadCount: number;
}>();
</script>

<template>
    <AppLayout>
        <Head title="Notifikasi" />

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Notifikasi</h1>
                    <p class="text-sm text-slate-600">{{ unreadCount }} belum dibaca</p>
                </div>
                <Link v-if="unreadCount > 0" href="/notifications/mark-all-read" method="post" as="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Tandai Semua Dibaca
                </Link>
            </div>

            <div v-if="notifications.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500">
                Belum ada notifikasi.
            </div>

            <div v-else class="space-y-2">
                <div v-for="n in notifications.data" :key="n.id" class="rounded-xl border bg-white p-4" :class="n.read_at ? 'border-slate-200' : 'border-blue-200 bg-blue-50/30'">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <p class="font-semibold text-slate-900">{{ n.title }}</p>
                            <p v-if="n.body" class="mt-1 text-sm text-slate-600">{{ n.body }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ n.created_at }}</p>
                        </div>
                        <Link v-if="!n.read_at" :href="`/notifications/${n.id}/read`" method="post" as="button" class="text-xs font-semibold text-blue-600 hover:underline">
                            Tandai dibaca
                        </Link>
                    </div>
                </div>
            </div>

            <nav v-if="notifications.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <template v-for="(link, index) in notifications.links" :key="index">
                    <Link v-if="link.url" :href="link.url" class="rounded-lg border px-3 py-1.5 text-sm" :class="link.active ? 'border-blue-500 bg-blue-50 font-semibold text-blue-600' : 'border-slate-100 text-slate-700 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>
