<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    html: { type: String, default: '' },
    fallback: { type: String, default: '<em>No content</em>' },
});

const showImages = ref(false);
const iframeRef = ref(null);
const iframeHeight = ref(100);

const hasRemoteImages = computed(() => {
    if (!props.html) return false;
    return /<img[^>]+src\s*=\s*["']https?:\/\//i.test(props.html);
});

const srcdocContent = computed(() => {
    let body = props.html || props.fallback;

    // Block remote images unless explicitly allowed.
    // Replace http(s) src with a data placeholder; keep data: and cid: as-is.
    if (!showImages.value) {
        body = body.replace(
            /(<img[^>]+src\s*=\s*["'])https?:\/\/[^"']+/gi,
            '$1data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22%3E%3Crect width=%2216%22 height=%2216%22 fill=%22%23e5e7eb%22/%3E%3C/svg%3E'
        );
    }

    return `<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src ${showImages.value ? 'https: http: data: cid:' : 'data: cid:'};">
<style>
  body {
    margin: 0; padding: 0; font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 14px; line-height: 1.6; color: #374151;
    word-wrap: break-word; overflow-wrap: break-word;
  }
  img { max-width: 100%; height: auto; }
  a { color: #4f46e5; }
  pre { overflow-x: auto; background: #f3f4f6; padding: 8px; border-radius: 4px; }
  blockquote { border-left: 3px solid #d1d5db; margin: 8px 0; padding-left: 12px; color: #6b7280; }
  table { border-collapse: collapse; }
  td, th { border: 1px solid #e5e7eb; padding: 4px 8px; }
</style>
</head>
<body>${body}</body>
</html>`;
});

function resizeIframe() {
    if (!iframeRef.value) return;
    try {
        // Cannot access contentDocument due to sandbox — use a fallback.
        // The iframe posts its height via a resize observer injected below,
        // but since we use sandbox="" (no scripts, no same-origin), we rely
        // on a minimum height and a generous max.
        // For sandboxed content we estimate from the HTML length.
        const estimated = Math.max(100, Math.min(2000, props.html ? props.html.length / 3 : 100));
        iframeHeight.value = estimated;
    } catch {
        iframeHeight.value = 300;
    }
}

watch(() => props.html, () => nextTick(resizeIframe));
watch(showImages, () => nextTick(resizeIframe));
onMounted(resizeIframe);
</script>

<template>
    <div>
        <iframe
            ref="iframeRef"
            :srcdoc="srcdocContent"
            sandbox=""
            :style="{ width: '100%', height: iframeHeight + 'px', border: 'none', overflow: 'hidden' }"
            referrerpolicy="no-referrer"
            loading="lazy"
        />
        <div v-if="hasRemoteImages && !showImages" class="mt-2 flex items-center gap-2">
            <button
                type="button"
                class="text-xs text-gray-500 hover:text-gray-700 underline"
                @click="showImages = true"
            >
                Load remote images
            </button>
            <span class="text-xs text-gray-400">(may contain tracking pixels)</span>
        </div>
    </div>
</template>
