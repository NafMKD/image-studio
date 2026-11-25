<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

type Column = { key: string; label: string; align?: 'left' | 'right' };

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: Record<string, string | number>[];
        empty?: string;
        pageSize?: number;
    }>(),
    {
        empty: 'No results.',
        pageSize: 8,
    },
);

const page = ref(1);
const pages = computed(() =>
    Math.max(1, Math.ceil(props.rows.length / props.pageSize)),
);
const visibleRows = computed(() =>
    props.rows.slice(
        (page.value - 1) * props.pageSize,
        page.value * props.pageSize,
    ),
);
watch(
    () => props.rows.length,
    () => {
        page.value = Math.min(page.value, pages.value);
    },
);
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="h-11 px-4 text-xs font-medium tracking-wide uppercase"
                            :class="
                                column.align === 'right'
                                    ? 'text-right'
                                    : 'text-left'
                            "
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in visibleRows"
                        :key="String(row.id ?? index)"
                        class="border-b last:border-0 hover:bg-muted/30"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3.5"
                            :class="
                                column.align === 'right'
                                    ? 'text-right tabular-nums'
                                    : ''
                            "
                        >
                            {{ row[column.key] }}
                        </td>
                    </tr>
                    <tr v-if="!visibleRows.length">
                        <td
                            :colspan="columns.length"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{ empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="pages > 1"
            class="flex items-center justify-between border-t px-4 py-3"
        >
            <span class="text-sm text-muted-foreground"
                >Page {{ page }} of {{ pages }}</span
            >
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="icon-sm"
                    :disabled="page === 1"
                    aria-label="Previous page"
                    @click="page--"
                    ><ChevronLeft
                /></Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    :disabled="page === pages"
                    aria-label="Next page"
                    @click="page++"
                    ><ChevronRight
                /></Button>
            </div>
        </div>
    </div>
</template>
