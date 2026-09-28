<template>
  <main class="blog-page">
    <!-- Hero -->
    <section class="blog-hero">
      <div class="container">
        <span class="eyebrow">{{ $t('blog.eyebrow') }}</span>
        <h1 class="display-lg blog-hero__title">{{ $t('blog.title') }}</h1>
        <p class="blog-hero__sub">{{ $t('blog.desc') }}</p>
      </div>
    </section>

    <div class="container blog-body">
      <!-- Filters -->
      <aside class="blog-sidebar">
        <h3 class="blog-sidebar__title">{{ $t('blog.categories') }}</h3>
        <button @click="activeCategory = null" :class="['blog-cat-btn', { active: !activeCategory }]">
          {{ $t('blog.allPosts') }}
        </button>
        <button v-for="cat in categories" :key="cat.id"
          @click="selectCategory(cat.slug)"
          :class="['blog-cat-btn', { active: activeCategory === cat.slug }]">
          {{ cat.name }}
          <span class="blog-cat-count">{{ cat.posts_count }}</span>
        </button>
      </aside>

      <!-- Posts grid -->
      <div class="blog-main">
        <!-- Search bar -->
        <div class="blog-topbar">
          <div class="blog-search">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="blog-search__icon">
              <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
            </svg>
            <input v-model="search" @input="debouncedFetch" type="text"
              :placeholder="$t('blog.searchPlaceholder')" class="blog-search__input" />
          </div>
          <p class="blog-topbar__count" v-if="!loading">{{ $t('blog.articleCount', { count: meta?.total ?? posts.length }, meta?.total ?? posts.length) }}</p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="blog-loader">
          <div class="blog-loader__spinner"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="!posts.length" class="blog-empty">
          <div class="blog-empty__icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></div>
          <h2 class="display-md">{{ $t('blog.noArticle') }}</h2>
          <p class="blog-empty__sub">{{ $t('blog.noArticleDesc') }}</p>
        </div>

        <!-- Grid -->
        <div v-else class="blog-grid">
          <article v-for="post in posts" :key="post.id" class="blog-card card animate-fade-up">
            <RouterLink :to="{ name: 'blog.post', params: { slug: post.slug } }" class="blog-card__img-wrap">
              <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" class="blog-card__img" />
              <div v-else class="blog-card__img-placeholder">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                  <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/>
                  <rect x="3" y="3" width="18" height="18" rx="2"/>
                </svg>
              </div>
            </RouterLink>

            <div class="blog-card__body">
              <div class="blog-card__meta">
                <span v-if="post.category" class="blog-card__cat">{{ post.category.name }}</span>
                <span class="blog-card__date">{{ formatDate(post.published_at || post.created_at) }}</span>
                <span v-if="post.reading_time" class="blog-card__read">{{ post.reading_time }} min</span>
              </div>

              <RouterLink :to="{ name: 'blog.post', params: { slug: post.slug } }">
                <h2 class="blog-card__title">{{ post.title }}</h2>
              </RouterLink>

              <p v-if="post.excerpt" class="blog-card__excerpt">{{ post.excerpt }}</p>

              <RouterLink :to="{ name: 'blog.post', params: { slug: post.slug } }" class="blog-card__link">
                {{ $t('blog.readArticle') }}
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </RouterLink>
            </div>
          </article>
        </div>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="blog-pagination">
          <button :disabled="meta.current_page === 1" @click="fetchPosts(meta.current_page - 1)" class="blog-pag-btn">
            {{ $t('blog.previous') }}
          </button>
          <span class="blog-pag-info">{{ $t('blog.page', { current: meta.current_page, total: meta.last_page }) }}</span>
          <button :disabled="meta.current_page === meta.last_page" @click="fetchPosts(meta.current_page + 1)" class="blog-pag-btn">
            {{ $t('blog.next') }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { useSeo, breadcrumbJsonLd } from '@/composables/useSeo'

const { t } = useI18n()

useSeo(() => ({
  title: t('blog.seoTitle'),
  description: t('blog.seoDescription'),
  canonical: '/blog',
  jsonLd: breadcrumbJsonLd([
    { name: t('common.home'), path: '/' },
    { name: t('common.blog'), path: '/blog' },
  ]),
}))

const posts          = ref([])
const categories     = ref([])
const loading        = ref(true)
const search         = ref('')
const activeCategory = ref(null)
const meta           = ref(null)

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchPosts(1), 350)
}

async function fetchPosts(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/blog', {
      params: {
        page,
        search:   search.value   || undefined,
        category: activeCategory.value || undefined,
      },
    })
    posts.value = data.data
    meta.value  = data.meta
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const { data } = await api.get('/blog/categories')
    categories.value = data.data ?? data
  } catch {
    // catégories non critiques — le blog reste fonctionnel sans elles
  }
}

function selectCategory(slug) {
  activeCategory.value = activeCategory.value === slug ? null : slug
  fetchPosts(1)
}

function formatDate(val) {
  if (!val) return ''
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

onMounted(() => Promise.all([fetchPosts(), fetchCategories()]))
</script>

<style scoped>
.blog-page { min-height: 100vh; background: var(--color-bg); }

/* ── Hero ── */
.blog-hero {
  background: linear-gradient(135deg, var(--rose-50) 0%, #fff 60%);
  border-bottom: 1px solid var(--cream-200);
  padding: var(--space-12) 0 var(--space-10);
}
.blog-hero__title {
  color: var(--gray-800);
  margin-top: var(--space-2);
}
.blog-hero__sub {
  color: var(--gray-500);
  font-size: 1rem;
  margin-top: var(--space-2);
}

/* ── Layout ── */
.blog-body {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: var(--space-10);
  padding-top: var(--space-8);
  padding-bottom: var(--space-16);
}
@media (max-width: 900px) {
  .blog-body { grid-template-columns: 1fr; }
  .blog-sidebar { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; }
  .blog-sidebar__title { width: 100%; }
}

/* ── Sidebar ── */
.blog-sidebar__title {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--gray-400);
  margin-bottom: var(--space-3);
}
.blog-cat-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  color: var(--gray-600);
  background: transparent;
  transition: all var(--transition-fast);
  margin-bottom: 2px;
}
.blog-cat-btn:hover { background: var(--rose-50); color: var(--rose-600); }
.blog-cat-btn.active { background: var(--rose-50); color: var(--rose-600); font-weight: 600; }
.blog-cat-count {
  font-size: 0.6875rem;
  background: var(--cream-200);
  color: var(--gray-500);
  padding: 1px 7px;
  border-radius: var(--radius-full);
}

/* ── Topbar ── */
.blog-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-6);
  flex-wrap: wrap;
}
.blog-search { position: relative; }
.blog-search__icon {
  position: absolute;
  left: 10px; top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
}
.blog-search__input {
  padding: 9px 12px 9px 32px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  font-size: 0.875rem;
  background: #fff;
  color: var(--gray-800);
  width: 260px;
  transition: border-color var(--transition-fast);
}
.blog-search__input:focus { outline: none; border-color: var(--rose-400); }
.blog-topbar__count { font-size: 0.8125rem; color: var(--gray-400); }

/* ── Loading / Empty ── */
.blog-loader { display: flex; justify-content: center; padding: var(--space-16) 0; }
.blog-loader__spinner {
  width: 36px; height: 36px;
  border: 2.5px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.blog-empty { text-align: center; padding: var(--space-16) 0; }
.blog-empty__icon { font-size: 3rem; margin-bottom: var(--space-3); }
.blog-empty__sub { color: var(--gray-400); margin-top: var(--space-2); font-size: 0.9375rem; }

/* ── Grid ── */
.blog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--space-6);
}

/* ── Card ── */
.blog-card { overflow: hidden; }
.blog-card__img-wrap {
  display: block;
  aspect-ratio: 16/9;
  overflow: hidden;
  background: var(--cream-100);
}
.blog-card__img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform var(--transition-normal);
}
.blog-card:hover .blog-card__img { transform: scale(1.04); }
.blog-card__img-placeholder {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-300);
}

.blog-card__body { padding: var(--space-5); }
.blog-card__meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
  margin-bottom: var(--space-2);
}
.blog-card__cat {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--rose-500);
  background: var(--rose-50);
  padding: 2px 8px;
  border-radius: var(--radius-full);
}
.blog-card__date,
.blog-card__read { font-size: 0.75rem; color: var(--gray-400); }
.blog-card__read::before { content: '·'; margin-right: var(--space-1); }

.blog-card__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
  line-height: 1.3;
  margin-bottom: var(--space-2);
  transition: color var(--transition-fast);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.blog-card:hover .blog-card__title { color: var(--rose-600); }

.blog-card__excerpt {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.6;
  margin-bottom: var(--space-4);
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.blog-card__link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--rose-500);
  transition: gap var(--transition-fast);
}
.blog-card__link:hover { gap: var(--space-2); }

/* ── Pagination ── */
.blog-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
  margin-top: var(--space-10);
}
.blog-pag-btn {
  padding: 8px 20px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 0.875rem;
  color: var(--gray-600);
  transition: all var(--transition-fast);
}
.blog-pag-btn:hover:not(:disabled) { border-color: var(--rose-300); color: var(--rose-500); }
.blog-pag-btn:disabled { opacity: 0.4; cursor: default; }
.blog-pag-info { font-size: 0.875rem; color: var(--gray-400); }
</style>
