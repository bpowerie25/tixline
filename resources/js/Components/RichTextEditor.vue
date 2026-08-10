<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import { watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { class: 'text-indigo-600 underline' },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[300px] px-4 py-3',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

function setLink() {
    const url = prompt('Enter URL:');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}

function isActive(name, attrs) {
    return editor.value?.isActive(name, attrs) ?? false;
}
</script>

<template>
    <div class="rounded-md border border-gray-300 shadow-sm overflow-hidden focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
            <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="isActive('bold') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5 text-sm font-bold" title="Bold">B</button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="isActive('italic') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5 text-sm italic" title="Italic">I</button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
                :class="isActive('underline') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5 text-sm underline" title="Underline">U</button>

            <div class="mx-1 h-5 w-px bg-gray-300"></div>

            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="isActive('heading', { level: 2 }) ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded px-1.5 py-1 text-xs font-bold" title="Heading">H2</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="isActive('heading', { level: 3 }) ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded px-1.5 py-1 text-xs font-bold" title="Subheading">H3</button>

            <div class="mx-1 h-5 w-px bg-gray-300"></div>

            <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
                :class="isActive('bulletList') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5" title="Bullet List">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
                :class="isActive('orderedList') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded px-1.5 py-1 text-xs font-mono" title="Numbered List">1.</button>

            <div class="mx-1 h-5 w-px bg-gray-300"></div>

            <button type="button" @click="setLink"
                :class="isActive('link') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5" title="Add Link">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
            </button>

            <button type="button" @click="editor.chain().focus().toggleBlockquote().run()"
                :class="isActive('blockquote') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded p-1.5" title="Quote">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
            </button>

            <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()"
                :class="isActive('codeBlock') ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded px-1.5 py-1 text-xs font-mono" title="Code Block">&lt;/&gt;</button>

            <div class="mx-1 h-5 w-px bg-gray-300"></div>

            <button type="button" @click="editor.chain().focus().setHorizontalRule().run()"
                class="rounded p-1.5 text-gray-600 hover:bg-gray-100" title="Horizontal Rule">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 12h18" /></svg>
            </button>
        </div>

        <!-- Editor -->
        <EditorContent :editor="editor" />
    </div>
</template>
