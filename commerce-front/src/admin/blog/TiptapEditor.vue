<template>
  <div class="tiptap-wrap">
    <!-- Toolbar -->
    <div class="tiptap-toolbar" v-if="editor">
      <button type="button" @click="editor.chain().focus().toggleBold().run()"
        :class="{ active: editor.isActive('bold') }" title="Gras">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5S13.83 9.5 13 9.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleItalic().run()"
        :class="{ active: editor.isActive('italic') }" title="Italique">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4h-8z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleStrike().run()"
        :class="{ active: editor.isActive('strike') }" title="Barré">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 19h4v-3h-4v3zM5 4v3h6v3h2V7h6V4H5zM3 14h18v-2H3v2z"/></svg>
      </button>

      <div class="tiptap-toolbar__sep"></div>

      <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="{ active: editor.isActive('heading', { level: 2 }) }" title="Titre H2">
        <span style="font-weight:700;font-size:11px">H2</span>
      </button>
      <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
        :class="{ active: editor.isActive('heading', { level: 3 }) }" title="Titre H3">
        <span style="font-weight:700;font-size:11px">H3</span>
      </button>

      <div class="tiptap-toolbar__sep"></div>

      <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
        :class="{ active: editor.isActive('bulletList') }" title="Liste">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
        :class="{ active: editor.isActive('orderedList') }" title="Liste numérotée">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleBlockquote().run()"
        :class="{ active: editor.isActive('blockquote') }" title="Citation">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" title="Séparateur">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13H5v-2h14v2z"/></svg>
      </button>

      <div class="tiptap-toolbar__sep"></div>

      <button type="button" @click="setLink" :class="{ active: editor.isActive('link') }" title="Lien">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().unsetLink().run()" v-if="editor.isActive('link')" title="Supprimer lien">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.95 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16v-2zM2 4.27l3.11 3.11C3.29 8.12 2 9.91 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.59 1.21-2.9 2.76-3.07L8.73 11H8v2h2.73L13 15.27V17h1.73l2.54 2.54L18.73 18 3.27 2.54 2 4.27z"/></svg>
      </button>

      <div class="tiptap-toolbar__sep"></div>

      <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" title="Annuler">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" title="Refaire">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
      </button>

      <span class="tiptap-toolbar__count" v-if="editor">
        {{ editor.storage.characterCount.words() }} mots
      </span>
    </div>

    <!-- Editor -->
    <editor-content :editor="editor" class="tiptap-editor" />
  </div>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import CharacterCount from '@tiptap/extension-character-count'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Rédigez votre article ici…' },
})
const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Link.configure({ openOnClick: false, HTMLAttributes: { class: 'tiptap-link' } }),
    Placeholder.configure({ placeholder: props.placeholder }),
    CharacterCount,
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
  editorProps: {
    attributes: { class: 'tiptap-content' },
  },
})

watch(() => props.modelValue, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val, false)
  }
})

onBeforeUnmount(() => editor.value?.destroy())

function setLink() {
  const prev = editor.value.getAttributes('link').href
  const url  = window.prompt('URL du lien :', prev)
  if (url === null) return
  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}
</script>

<style scoped>
.tiptap-wrap {
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: #fff;
  transition: border-color var(--transition-fast);
}
.tiptap-wrap:focus-within { border-color: var(--rose-400); }

/* ── Toolbar ── */
.tiptap-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
  padding: 8px 10px;
  background: var(--cream-50);
  border-bottom: 1px solid var(--cream-200);
}
.tiptap-toolbar button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  color: var(--gray-500);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.tiptap-toolbar button:hover:not(:disabled) { background: var(--cream-200); color: var(--gray-800); }
.tiptap-toolbar button.active { background: var(--rose-100); color: var(--rose-600); }
.tiptap-toolbar button:disabled { opacity: 0.35; cursor: default; }
.tiptap-toolbar__sep {
  width: 1px;
  height: 20px;
  background: var(--cream-300);
  margin: 0 4px;
}
.tiptap-toolbar__count {
  margin-left: auto;
  font-size: 0.6875rem;
  color: var(--gray-400);
}
</style>

<style>
/* Global — styles du contenu éditeur (non scoped pour cibler .ProseMirror) */
.tiptap-editor .ProseMirror {
  min-height: 320px;
  padding: 16px 20px;
  outline: none;
  font-size: 0.9375rem;
  line-height: 1.75;
  color: var(--gray-700);
}

.tiptap-editor .ProseMirror p { margin-bottom: 0.875rem; }
.tiptap-editor .ProseMirror p:last-child { margin-bottom: 0; }

.tiptap-editor .ProseMirror h2 {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  color: var(--gray-800);
  margin: 1.5rem 0 0.625rem;
  line-height: 1.2;
}
.tiptap-editor .ProseMirror h3 {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
  margin: 1.25rem 0 0.5rem;
}
.tiptap-editor .ProseMirror strong { font-weight: 600; color: var(--gray-800); }
.tiptap-editor .ProseMirror em { font-style: italic; }
.tiptap-editor .ProseMirror s { text-decoration: line-through; color: var(--gray-400); }

.tiptap-editor .ProseMirror ul,
.tiptap-editor .ProseMirror ol {
  padding-left: 1.5rem;
  margin-bottom: 0.875rem;
}
.tiptap-editor .ProseMirror li { margin-bottom: 0.25rem; }

.tiptap-editor .ProseMirror blockquote {
  border-left: 3px solid var(--rose-300);
  padding: 0.5rem 1rem;
  margin: 1rem 0;
  color: var(--gray-500);
  font-style: italic;
  background: var(--rose-50);
  border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}
.tiptap-editor .ProseMirror hr {
  border: none;
  border-top: 1px solid var(--cream-300);
  margin: 1.5rem 0;
}
.tiptap-editor .ProseMirror a.tiptap-link {
  color: var(--rose-500);
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* Placeholder */
.tiptap-editor .ProseMirror p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  color: var(--gray-300);
  pointer-events: none;
  float: left;
  height: 0;
}
</style>
