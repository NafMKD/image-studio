<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    balance: string;
    available: string;
    spent: string;
    succeeded: number;
    history: { id: number; type: string; amount: string; date: string }[];
}>();

const columns = [
    { key: 'activity', label: 'Activity' },
    { key: 'date', label: 'Date' },
    { key: 'amount', label: 'Amount', align: 'right' as const },
];
const rows = computed(() =>
    props.history.map((entry) => ({
        id: entry.id,
        activity:
            entry.type === 'topup' ? 'Demo credits added' : 'Image generation',
        date: `${entry.date} UTC`,
        amount: entry.amount,
    })),
);
</script>

<template>
    <Head title="Dashboard" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Credits and generation activity
                </p>
            </div>
            <Button @click="router.post('/credits')"
                ><Wallet /> Add demo credits</Button
            >
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="stat in [
                    { label: 'Balance', value: balance },
                    { label: 'Available', value: available },
                    { label: 'Total spent', value: spent },
                    { label: 'Completed', value: String(succeeded) },
                ]"
                :key="stat.label"
            >
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-sm font-medium text-muted-foreground"
                        >{{ stat.label }}</CardTitle
                    ></CardHeader
                >
                <CardContent class="text-2xl font-semibold tabular-nums">{{
                    stat.value
                }}</CardContent>
            </Card>
        </div>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Credit activity</h2>
            <DataTable
                :columns="columns"
                :rows="rows"
                empty="No credit activity yet."
            />
        </section>
    </main>
</template>
