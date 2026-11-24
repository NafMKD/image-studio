<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ArrowUp, Menu, Plus, RefreshCw, Sparkles, Wallet, X } from '@lucide/vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'

type Message = {
  id: number; prompt: string; status: string; size: string; quality: string
  image_url: string | null; error: string | null; estimated: string; actual: string | null
}
type Chat = { id: number; title: string }
const props = defineProps<{
  chats: Chat[]
  conversation: { id: number; messages: Message[] } | null
  wallet: { balance: string; available: string }
  isAdmin: boolean
  demoMode: boolean
}>()

const form = useForm({
  client_request_id: crypto.randomUUID(),
  conversation_id: props.conversation?.id ?? null as number | null,
  prompt: '', size: '1024x1024', quality: 'medium', reference_urls: '',
})
watch(() => props.conversation?.id, id => { form.conversation_id = id ?? null })

const sidebarOpen = ref(false)
const showOptions = ref(false)
const estimate = ref('—')
const hold = ref('—')
const pricingError = ref('')
const hasPending = computed(() => props.conversation?.messages.some(m => ['submitting', 'queued', 'processing'].includes(m.status)) ?? false)
const referenceCount = computed(() => form.reference_urls.split(/\r?\n/).filter(url => url.trim()).length)

let estimateTimer: ReturnType<typeof setTimeout> | undefined
let pollTimer: ReturnType<typeof setInterval> | undefined
let serial = 0
watch([() => form.prompt, () => form.size, () => form.quality, referenceCount], () => {
  clearTimeout(estimateTimer)
  const requestNumber = ++serial // Older responses must never replace a newer estimate.
  estimateTimer = setTimeout(async () => {
    const query = new URLSearchParams({
      characters: String(Array.from(form.prompt).length),
      references: String(referenceCount.value), size: form.size, quality: form.quality,
    })
    try {
      const response = await fetch(`/studio/estimate?${query}`, { headers: { Accept: 'application/json' } })
      if (!response.ok) throw new Error('Choose up to 4 reference URLs.')
      const result: { estimated: string; hold: string } = await response.json()
      if (requestNumber === serial) { estimate.value = result.estimated; hold.value = result.hold; pricingError.value = '' }
    } catch (error) {
      if (requestNumber === serial) pricingError.value = (error as Error).message
    }
  }, 300)
}, { immediate: true })

function submit() {
  if (form.processing || form.prompt.trim().length < 3) return
  form.post('/studio/generations', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('prompt', 'reference_urls')
      form.client_request_id = crypto.randomUUID()
      showOptions.value = false
    },
  })
}
function keydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    submit()
  }
}
function refresh() { router.reload({ only: ['conversation', 'chats', 'wallet'], preserveScroll: true }) }
function topUp() { router.post('/credits', {}, { preserveScroll: true }) }

onMounted(() => { pollTimer = setInterval(() => {
  if (hasPending.value && document.visibilityState === 'visible') refresh()
}, 5000) })
onUnmounted(() => { clearInterval(pollTimer); clearTimeout(estimateTimer) })
</script>

<template>
  <Head title="Studio" />
  <div class="min-h-screen bg-white text-zinc-900">
    <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/30 md:hidden" @click="sidebarOpen = false" />
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
      class="fixed inset-y-0 left-0 z-30 flex w-72 flex-col border-r border-zinc-200 bg-[#f9f9f9] p-3 transition-transform md:transition-none">
      <div class="mb-5 flex items-center justify-between px-2 pt-2">
        <Link href="/studio" class="flex items-center gap-2 text-lg font-semibold tracking-tight" @click="sidebarOpen = false">
          <span class="grid size-9 place-items-center rounded-xl bg-zinc-900 text-white"><Sparkles :size="18" /></span>
          PromptForge
        </Link>
        <button class="rounded-md p-1 hover:bg-zinc-200 md:hidden" aria-label="Close sidebar" @click="sidebarOpen = false"><X :size="20" /></button>
      </div>
      <Link href="/studio" class="mb-5 flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium shadow-sm hover:bg-zinc-50" @click="sidebarOpen = false">
        <Plus :size="17" /> New generation chat
      </Link>
      <div class="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">Recent chats</div>
      <nav class="min-h-0 flex-1 space-y-0.5 overflow-auto" aria-label="Generation history">
        <Link v-for="chat in chats" :key="chat.id" :href="`/studio/${chat.id}`"
          :class="conversation?.id === chat.id ? 'bg-zinc-200/70 font-medium' : 'hover:bg-zinc-200/60'"
          class="block truncate rounded-lg px-3 py-2.5 text-sm text-zinc-700" @click="sidebarOpen = false">
          {{ chat.title }}
        </Link>
        <p v-if="chats.length === 0" class="px-3 py-3 text-sm text-zinc-400">Your chats will appear here.</p>
      </nav>
      <div class="mt-3 space-y-2 border-t border-zinc-200 pt-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-3">
          <div class="flex items-center justify-between text-xs text-zinc-500"><span>Available credits</span><Wallet :size="15" /></div>
          <div class="mt-1 truncate text-xl font-semibold tabular-nums">{{ wallet.available }}</div>
          <Button variant="outline" size="sm" class="mt-3 w-full" @click="topUp">+ $10 demo credits</Button>
        </div>
        <div class="flex flex-wrap gap-3 px-2 text-xs text-zinc-500">
          <Link href="/account" class="hover:text-zinc-900">Dashboard</Link>
          <Link v-if="isAdmin" href="/admin" class="hover:text-zinc-900">Super admin</Link>
          <Link href="/settings/profile" class="hover:text-zinc-900">Profile</Link>
          <Link href="/logout" method="post" as="button" class="hover:text-zinc-900">Sign out</Link>
        </div>
      </div>
    </aside>

    <main class="flex min-h-screen flex-col md:pl-72">
      <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-zinc-100 bg-white/95 px-4 backdrop-blur md:px-8">
        <div class="flex items-center gap-3">
          <button class="rounded-lg p-2 hover:bg-zinc-100 md:hidden" aria-label="Open sidebar" @click="sidebarOpen = true"><Menu :size="20" /></button>
          <div class="text-sm font-semibold">Image studio <span class="font-normal text-zinc-400">/ {{ conversation ? 'Conversation' : 'New chat' }}</span></div>
        </div>
        <div class="flex items-center gap-2">
          <Badge v-if="demoMode" variant="secondary">Local demo</Badge>
          <Button v-if="hasPending" variant="ghost" size="icon" aria-label="Refresh status" @click="refresh"><RefreshCw :size="16" /></Button>
        </div>
      </header>

      <section class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 pb-64 pt-7 sm:px-8">
        <div v-if="!conversation?.messages.length" class="flex flex-1 flex-col items-center justify-center pb-12 text-center">
          <div class="mb-5 grid size-16 place-items-center rounded-2xl bg-zinc-900 text-white"><Sparkles :size="28" /></div>
          <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">What will you create?</h1>
          <p class="mt-3 max-w-md text-sm leading-6 text-zinc-500">Describe an image, choose the output quality, and see the estimated charge before you generate.</p>
          <p v-if="demoMode" class="mt-3 text-xs text-zinc-400">Demo mode simulates processing and billing; no real images are generated.</p>
        </div>
        <div v-for="message in conversation?.messages ?? []" :key="message.id" class="mb-9 space-y-4">
          <div class="ml-auto max-w-[90%] rounded-2xl bg-[#f3f3f3] px-4 py-3 text-sm leading-6 whitespace-pre-wrap">{{ message.prompt }}</div>
          <div class="space-y-3 rounded-2xl border border-zinc-100 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="text-xs font-medium capitalize text-zinc-500">{{ message.size }} · {{ message.quality }}</span>
              <Badge :variant="message.status === 'failed' || message.status === 'uncertain' ? 'destructive' : 'secondary'">{{ message.status }}</Badge>
            </div>
            <img v-if="message.image_url" :src="message.image_url" :alt="message.prompt" loading="lazy" referrerpolicy="no-referrer" class="w-full rounded-xl border object-contain" />
            <div v-else-if="message.status === 'succeeded'" class="flex aspect-video items-center justify-center rounded-xl bg-gradient-to-tr from-zinc-200 via-stone-100 to-slate-200 p-8 text-center text-sm text-zinc-500">Simulated image · {{ message.prompt }}</div>
            <div v-else-if="['queued', 'submitting', 'processing'].includes(message.status)" class="flex items-center gap-2 text-sm text-zinc-500"><span class="size-2 animate-pulse rounded-full bg-amber-500" />Generating your image…</div>
            <p v-else class="text-sm text-red-600">{{ message.error || 'Status needs review.' }}</p>
            <div class="text-xs text-zinc-500">Estimated {{ message.estimated }} <span class="mx-1">·</span> Actual {{ message.actual ?? 'pending' }}</div>
          </div>
        </div>
      </section>

      <footer class="fixed inset-x-0 bottom-0 z-10 bg-gradient-to-t from-white via-white to-white/90 px-3 pb-4 pt-6 md:left-72 sm:px-6">
        <div class="mx-auto max-w-3xl rounded-3xl border border-zinc-200 bg-white p-3 shadow-[0_12px_50px_rgba(0,0,0,0.09)]">
          <form @submit.prevent="submit">
            <Textarea v-model="form.prompt" :maxlength="4000" rows="3" placeholder="Describe the image you want to generate…" class="max-h-52 min-h-20 resize-none border-0 bg-transparent shadow-none focus-visible:ring-0" @keydown="keydown" />
            <div v-if="showOptions" class="grid gap-3 border-t border-zinc-100 px-2 pb-3 pt-3 sm:grid-cols-2">
              <label class="text-xs font-medium text-zinc-500">Size
                <select v-model="form.size" class="mt-1.5 w-full rounded-lg border border-zinc-200 bg-white p-2 text-sm text-zinc-900">
                  <option value="1024x1024">Square 1024 x 1024</option><option value="1536x1024">Landscape 1536 x 1024</option><option value="1024x1536">Portrait 1024 x 1536</option>
                </select>
              </label>
              <label class="text-xs font-medium text-zinc-500">Quality
                <select v-model="form.quality" class="mt-1.5 w-full rounded-lg border border-zinc-200 bg-white p-2 text-sm text-zinc-900">
                  <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option>
                </select>
              </label>
              <label class="text-xs font-medium text-zinc-500 sm:col-span-2">Reference image URLs (one public HTTP(S) URL per line; max 4)
                <Textarea v-model="form.reference_urls" rows="2" class="mt-1.5 resize-none" placeholder="https://example.com/reference.jpg" />
              </label>
            </div>
            <div v-if="Object.keys(form.errors).length" class="px-2 pb-2 text-xs text-red-600">{{ Object.values(form.errors)[0] }}</div>
            <div class="flex items-center justify-between gap-2 px-1 pt-2">
              <div class="flex min-w-0 items-center gap-2">
                <Button type="button" variant="ghost" size="sm" @click="showOptions = !showOptions">{{ showOptions ? 'Hide options' : 'Size · Quality · References' }}</Button>
                <span class="hidden text-xs text-zinc-400 sm:inline">{{ form.prompt.length }}/4000</span>
              </div>
              <Button type="submit" size="icon" class="rounded-full" :disabled="form.processing || form.prompt.trim().length < 3 || referenceCount > 4" aria-label="Generate image"><ArrowUp :size="17" /></Button>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-1 px-2 pt-2 text-xs text-zinc-500">
              <span v-if="pricingError" class="text-red-600">{{ pricingError }}</span>
              <span v-else>Estimate <strong class="text-zinc-800">{{ estimate }}</strong> · temporary hold up to {{ hold }}</span>
              <span>Actual charge uses completed token usage.</span>
            </div>
          </form>
        </div>
        <p class="mx-auto mt-2 max-w-3xl text-center text-[11px] text-zinc-400">Failed jobs cost nothing. Enter sends · Shift+Enter adds a new line.</p>
      </footer>
    </main>
  </div>
</template>
