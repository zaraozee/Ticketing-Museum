<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification');
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent'
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email Anda" />

        <template #subtitle>
            Terima kasih telah mendaftar!
        </template>

        <div class="mb-4 text-sm text-slate-600 leading-relaxed">
            Sebelum memulai, harap verifikasi alamat email Anda dengan mengeklik tautan yang baru saja kami kirimkan ke email Anda. Jika tidak menerima email, kami dapat mengirimkannya kembali.
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-emerald-600 bg-emerald-50 p-3 rounded-xl border border-emerald-200">
            Tautan verifikasi baru telah dikirimkan ke alamat email yang Anda daftarkan.
        </div>

        <form @submit.prevent="submit" class="mt-4 flex flex-col gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
            >
                {{ form.processing ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}
            </button>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="w-full text-center text-xs text-slate-600 hover:text-slate-900 underline py-2"
            >
                Keluar
            </Link>
        </form>
    </GuestLayout>
</template>
