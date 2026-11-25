<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    rows: {
        day: string;
        user: string;
        jobs: number;
        provider: string;
        charged: string;
        profit: string;
    }[];
    users: number;
    generations: number;
    pending: number;
    review: {
        id: number;
        client_request_id: string;
        email: string;
        created_at: string;
    }[];
}>();

const columns = [
    { key: 'day', label: 'Day' },
    { key: 'user', label: 'User' },
    { key: 'jobs', label: 'Jobs', align: 'right' as const },
    { key: 'provider', label: 'Provider cost', align: 'right' as const },
    { key: 'charged', label: 'Charged', align: 'right' as const },
    { key: 'profit', label: 'Profit', align: 'right' as const },
];
const tableRows = computed(() => props.rows.map((row, id) => ({ id, ...row })));
</script>

<template>
    <Head title="Super admin" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Super admin
                </h1>
                <p class="text-sm text-muted-foreground">
                    Usage and unit economics
                </p>
            </div>
            <Button variant="outline" as-child
                ><a
                    href="/admin/usage"
                    target="_blank"
                    rel="noopener noreferrer"
                    >Raw usage</a
                ></Button
            >
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card
                v-for="stat in [
                    { label: 'Users', value: users },
                    { label: 'Generations', value: generations },
                    { label: 'Needs review', value: pending },
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
            <h2 class="text-lg font-semibold">Daily unit economics</h2>
            <DataTable
                :columns="columns"
                :rows="tableRows"
                empty="No completed generations."
            />
        </section>

        <section
            v-if="review.length"
            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5"
        >
            <h2 class="font-semibold">Needs review</h2>
            <div
                v-for="item in review"
                :key="item.id"
                class="mt-3 border-t border-amber-500/20 pt-3 text-sm text-muted-foreground"
            >
                #{{ item.id }} · {{ item.email }} · {{ item.created_at }} ·
                {{ item.client_request_id }}
            </div>
        </section>
    </main>
</template>
