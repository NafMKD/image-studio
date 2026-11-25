<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowUp,
    LoaderCircle,
    Plus,
    RefreshCw,
    SlidersHorizontal,
    Sparkles,
    Wallet,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Message = {
    id: number;
    prompt: string;
    status: string;
    size: string;
    quality: string;
    image_url: string | null;
    error: string | null;
    estimated: string;
    actual: string | null;
};
type Chat = { id: number; title: string };
const props = defineProps<{
    chats: Chat[];
    conversation: { id: number; messages: Message[] } | null;
    wallet: { balance: string; available: string };
    isAdmin: boolean;
    demoMode: boolean;
}>();

const generationForm = useForm({
    client_request_id: crypto.randomUUID(),
    conversation_id: props.conversation?.id ?? (null as number | null),
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_urls: '',
});
const prompt = computed({
    get: () => generationForm.prompt,
    set: (value) => {
        generationForm.prompt = String(value);
    },
});
const size = computed({
    get: () => generationForm.size,
    set: (value) => {
        generationForm.size = String(value);
    },
});
const quality = computed({
    get: () => generationForm.quality,
    set: (value) => {
        generationForm.quality = String(value);
    },
});
const referenceUrls = computed({
    get: () => generationForm.reference_urls,
    set: (value) => {
        generationForm.reference_urls = String(value);
    },
});
const formErrors = computed(() => generationForm.errors);
const isProcessing = computed(() => generationForm.processing);
const canSubmit = computed(
    () =>
        generationForm.prompt.trim().length >= 3 &&
        referenceCount.value <= 4 &&
        !generationForm.processing,
);
watch(
    () => props.conversation?.id,
    (id) => {
        generationForm.conversation_id = id ?? null;
    },
);

const showOptions = ref(false);
const estimate = ref('—');
const hold = ref('—');
const pricingError = ref('');
const hasPending = computed(
    () =>
        props.conversation?.messages.some((m) =>
            ['submitting', 'queued', 'processing'].includes(m.status),
        ) ?? false,
);
const referenceCount = computed(
    () =>
        generationForm.reference_urls.split(/\r?\n/).filter((url) => url.trim())
            .length,
);

let estimateTimer: ReturnType<typeof setTimeout> | undefined;
let pollTimer: ReturnType<typeof setInterval> | undefined;
let serial = 0;
watch(
    [
        () => generationForm.prompt,
        () => generationForm.size,
        () => generationForm.quality,
        referenceCount,
    ],
    () => {
        clearTimeout(estimateTimer);
        const requestNumber = ++serial;
        estimateTimer = setTimeout(async () => {
            const query = new URLSearchParams({
                characters: String(Array.from(generationForm.prompt).length),
                references: String(referenceCount.value),
                size: generationForm.size,
                quality: generationForm.quality,
            });
            try {
                const response = await fetch(`/studio/estimate?${query}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok)
                    throw new Error('Choose up to 4 reference URLs.');
                const result: { estimated: string; hold: string } =
                    await response.json();
                if (requestNumber === serial) {
                    estimate.value = result.estimated;
                    hold.value = result.hold;
                    pricingError.value = '';
                }
            } catch (error) {
                if (requestNumber === serial)
                    pricingError.value = (error as Error).message;
            }
        }, 300);
    },
    { immediate: true },
);

function submit() {
    if (generationForm.processing || generationForm.prompt.trim().length < 3)
        return;
    generationForm.post('/studio/generations', {
        preserveScroll: true,
        onSuccess: () => {
            generationForm.reset('prompt', 'reference_urls');
            generationForm.client_request_id = crypto.randomUUID();
            showOptions.value = false;
        },
    });
}
function keydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        submit();
    }
}
function refresh() {
    router.reload({ only: ['conversation', 'chats', 'wallet'] });
}
function topUp() {
    router.post('/credits', {}, { preserveScroll: true });
}

onMounted(() => {
    pollTimer = setInterval(() => {
        if (hasPending.value && document.visibilityState === 'visible')
            refresh();
    }, 5000);
});
onUnmounted(() => {
    clearInterval(pollTimer);
    clearTimeout(estimateTimer);
});
</script>

<template>
    <Head title="Studio" />
    <div
        class="grid min-h-[calc(100vh-4rem)] gap-4 p-4 lg:grid-cols-[240px_minmax(0,1fr)]"
    >
        <aside
            class="flex max-h-72 flex-col rounded-xl border bg-card p-3 lg:max-h-[calc(100vh-6rem)]"
        >
            <Button as-child class="w-full justify-start"
                ><Link href="/studio"><Plus /> New chat</Link></Button
            >
            <p
                class="mt-5 mb-2 px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Recent chats
            </p>
            <nav
                class="min-h-24 flex-1 space-y-1 overflow-auto"
                aria-label="Generation history"
            >
                <Link
                    v-for="chat in chats"
                    :key="chat.id"
                    :href="`/studio/${chat.id}`"
                    :class="
                        conversation?.id === chat.id
                            ? 'bg-accent text-accent-foreground'
                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground'
                    "
                    class="block truncate rounded-md px-3 py-2 text-sm"
                >
                    {{ chat.title }}
                </Link>
                <p
                    v-if="chats.length === 0"
                    class="px-3 py-3 text-sm text-muted-foreground"
                >
                    No chats yet.
                </p>
            </nav>
            <div class="mt-3 rounded-lg border bg-muted/30 p-3">
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>Available</span><Wallet :size="15" />
                </div>
                <div class="mt-1 truncate text-xl font-semibold tabular-nums">
                    {{ wallet.available }}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="mt-3 w-full"
                    @click="topUp"
                    >Add demo credits</Button
                >
            </div>
        </aside>

        <main
            class="flex min-h-[calc(100vh-6rem)] min-w-0 flex-col overflow-hidden rounded-xl border bg-card"
        >
            <header
                class="flex h-14 shrink-0 items-center justify-between border-b px-4 sm:px-6"
            >
                <div class="text-sm font-semibold">
                    Image studio
                    <span class="font-normal text-muted-foreground"
                        >/
                        {{ conversation ? 'Conversation' : 'New chat' }}</span
                    >
                </div>
                <div class="flex items-center gap-2">
                    <Badge v-if="demoMode" variant="secondary">Demo</Badge>
                    <Button
                        v-if="hasPending"
                        variant="ghost"
                        size="icon"
                        aria-label="Refresh status"
                        @click="refresh"
                        ><RefreshCw :size="16"
                    /></Button>
                </div>
            </header>

            <section
                class="mx-auto flex w-full max-w-3xl flex-1 flex-col overflow-y-auto px-4 py-7 sm:px-8"
            >
                <div
                    v-if="!conversation?.messages.length"
                    class="flex flex-1 flex-col items-center justify-center pb-12 text-center"
                >
                    <div
                        class="mb-5 grid size-14 place-items-center rounded-2xl bg-primary text-primary-foreground"
                    >
                        <Sparkles :size="25" />
                    </div>
                    <h1 class="text-3xl font-semibold tracking-tight">
                        What will you create?
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Describe your image and generate it.
                    </p>
                </div>
                <div
                    v-for="message in conversation?.messages ?? []"
                    :key="message.id"
                    class="mb-9 space-y-4"
                >
                    <div
                        class="ml-auto max-w-[90%] rounded-2xl bg-muted px-4 py-3 text-sm leading-6 whitespace-pre-wrap"
                    >
                        {{ message.prompt }}
                    </div>
                    <div class="space-y-3 rounded-2xl border p-4 sm:p-5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="text-xs font-medium text-muted-foreground capitalize"
                                >{{ message.size }} ·
                                {{ message.quality }}</span
                            >
                            <Badge
                                :variant="
                                    message.status === 'failed' ||
                                    message.status === 'uncertain'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                                >{{ message.status }}</Badge
                            >
                        </div>
                        <img
                            v-if="message.image_url"
                            :src="message.image_url"
                            :alt="message.prompt"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="w-full rounded-xl border object-contain"
                        />
                        <div
                            v-else-if="message.status === 'succeeded'"
                            class="flex aspect-video items-center justify-center rounded-xl bg-muted p-8 text-center text-sm text-muted-foreground"
                        >
                            Simulated image · {{ message.prompt }}
                        </div>
                        <div
                            v-else-if="
                                ['queued', 'submitting', 'processing'].includes(
                                    message.status,
                                )
                            "
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <LoaderCircle
                                class="animate-spin"
                                :size="16"
                            />Generating…
                        </div>
                        <p v-else class="text-sm text-destructive">
                            {{ message.error || 'Status needs review.' }}
                        </p>
                        <div class="text-xs text-muted-foreground">
                            Estimated {{ message.estimated }}
                            <span class="mx-1">·</span> Actual
                            {{ message.actual ?? 'pending' }}
                        </div>
                    </div>
                </div>
            </section>

            <footer
                class="shrink-0 border-t bg-background/95 px-3 py-4 backdrop-blur sm:px-6"
            >
                <div
                    class="mx-auto max-w-3xl rounded-2xl border bg-card p-3 shadow-sm focus-within:ring-2 focus-within:ring-ring/40"
                >
                    <form @submit.prevent="submit">
                        <Textarea
                            v-model="prompt"
                            :maxlength="4000"
                            rows="3"
                            placeholder="Describe the image you want to generate…"
                            class="max-h-52 min-h-20 resize-none border-0 bg-transparent text-base shadow-none focus-visible:ring-0"
                            @keydown="keydown"
                        />
                        <div
                            v-if="showOptions"
                            class="grid gap-3 border-t px-2 pt-3 pb-3 sm:grid-cols-2"
                        >
                            <label class="text-sm font-medium"
                                >Size
                                <Select v-model="size"
                                    ><SelectTrigger class="mt-1.5 w-full"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent>
                                        <SelectItem value="1024x1024"
                                            >Square 1024 × 1024</SelectItem
                                        ><SelectItem value="1536x1024"
                                            >Landscape 1536 × 1024</SelectItem
                                        ><SelectItem value="1024x1536"
                                            >Portrait 1024 × 1536</SelectItem
                                        >
                                    </SelectContent></Select
                                >
                            </label>
                            <label class="text-sm font-medium"
                                >Quality
                                <Select v-model="quality"
                                    ><SelectTrigger class="mt-1.5 w-full"
                                        ><SelectValue /></SelectTrigger
                                    ><SelectContent>
                                        <SelectItem value="low">Low</SelectItem
                                        ><SelectItem value="medium"
                                            >Medium</SelectItem
                                        ><SelectItem value="high"
                                            >High</SelectItem
                                        >
                                    </SelectContent></Select
                                >
                            </label>
                            <label class="text-sm font-medium sm:col-span-2"
                                >Reference URLs
                                <span class="font-normal text-muted-foreground"
                                    >(one per line, max 4)</span
                                >
                                <Textarea
                                    v-model="referenceUrls"
                                    rows="2"
                                    class="mt-1.5 resize-none"
                                    placeholder="https://example.com/reference.jpg"
                                />
                            </label>
                        </div>
                        <div
                            v-if="Object.keys(formErrors).length"
                            class="px-2 pb-2 text-xs text-destructive"
                        >
                            {{ Object.values(formErrors)[0] }}
                        </div>
                        <div
                            class="flex items-center justify-between gap-2 px-1 pt-2"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="showOptions = !showOptions"
                                    ><SlidersHorizontal /> Options</Button
                                >
                                <span
                                    class="hidden text-xs text-muted-foreground sm:inline"
                                    >{{ prompt.length }}/4000</span
                                >
                            </div>
                            <Button
                                type="submit"
                                size="icon"
                                class="rounded-full transition-all"
                                :class="
                                    canSubmit
                                        ? 'shadow-md'
                                        : 'bg-muted text-muted-foreground'
                                "
                                :disabled="!canSubmit"
                                :aria-label="
                                    isProcessing
                                        ? 'Generating image'
                                        : 'Generate image'
                                "
                            >
                                <LoaderCircle
                                    v-if="isProcessing"
                                    class="animate-spin"
                                    :size="17"
                                /><ArrowUp v-else :size="17" />
                            </Button>
                        </div>
                        <div class="px-2 pt-2 text-xs text-muted-foreground">
                            <span
                                v-if="pricingError"
                                class="text-destructive"
                                >{{ pricingError }}</span
                            >
                            <span v-else
                                >Estimate
                                <strong class="text-foreground">{{
                                    estimate
                                }}</strong>
                                · hold {{ hold }}</span
                            >
                        </div>
                    </form>
                </div>
            </footer>
        </main>
    </div>
</template>
