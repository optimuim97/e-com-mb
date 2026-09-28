<template>
  <main class="post-page">
    <!-- Loading -->
    <div v-if="loading" class="post-loader">
      <div class="post-loader__spinner"></div>
    </div>

    <!-- Not found -->
    <div v-else-if="!post" class="container post-notfound">
      <div class="post-notfound__icon"><FlowerMark /></div>
      <h1 class="display-md">{{ $t('blog.postNotFound') }}</h1>
      <RouterLink to="/blog" class="btn btn-primary mt-4">{{ $t('blog.backToBlog') }}</RouterLink>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Cover -->
      <div v-if="post.cover_image" class="post-cover">
        <img :src="post.cover_image" :alt="post.title" class="post-cover__img" />
        <div class="post-cover__overlay"></div>
      </div>

      <div class="container post-body">
        <!-- Breadcrumb -->
        <nav class="post-breadcrumb">
          <RouterLink to="/blog">Blog</RouterLink>
          <span>›</span>
          <span v-if="post.category">{{ post.category.name }}</span>
          <span>›</span>
          <span class="post-breadcrumb__current">{{ post.title }}</span>
        </nav>

        <div class="post-layout">
          <!-- Article -->
          <article class="post-article">
            <!-- Header -->
            <header class="post-header">
              <div class="post-header__meta">
                <span v-if="post.category" class="post-cat-badge">{{ post.category.name }}</span>
                <time class="post-header__date">{{ formatDate(post.published_at || post.created_at) }}</time>
                <span v-if="post.reading_time" class="post-header__read">{{ $t('blog.readingTime', { time: post.reading_time }) }}</span>
              </div>
              <h1 class="post-title">{{ post.title }}</h1>
              <p v-if="post.excerpt" class="post-excerpt">{{ post.excerpt }}</p>
              <div v-if="post.author" class="post-author">
                <div class="post-author__avatar">{{ post.author.name?.[0]?.toUpperCase() }}</div>
                <div>
                  <p class="post-author__name">{{ post.author.name }}</p>
                  <p class="post-author__role">{{ settings.shopName }}</p>
                </div>
              </div>
            </header>

            <!-- Vidéo de l'article -->
            <div v-if="post.video_url" class="post-video">
              <VideoPlayer
                :src="post.video_url"
                :poster="post.cover_image || ''"
                aspect-ratio="9 / 16"
                max-height="640px"
              />
            </div>

            <!-- Body content -->
            <div class="post-content prose" v-html="post.content"></div>

            <!-- Tags / Share -->
            <footer class="post-footer">
              <div class="divider-rose"></div>
              <div class="post-footer__inner">
                <RouterLink to="/blog" class="post-back-link">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M19 12H5M12 19l-7-7 7-7"/>
                  </svg>
                  {{ $t('blog.backToBlog') }}
                </RouterLink>
                <div class="post-share">
                  <span class="post-share__label">{{ $t('blog.share') }}</span>
                  <a :href="`https://twitter.com/intent/tweet?text=${encodeURIComponent(post.title)}&url=${encodeURIComponent(pageUrl)}`"
                    target="_blank" rel="noopener" class="post-share__btn" title="Twitter/X">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L2.25 2.25h6.775l4.253 5.622L18.244 2.25z"/>
                    </svg>
                  </a>
                  <a :href="`https://wa.me/?text=${encodeURIComponent(post.title + ' ' + pageUrl)}`"
                    target="_blank" rel="noopener" class="post-share__btn post-share__btn--wa" title="WhatsApp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                  </a>
                </div>
              </div>
            </footer>
          </article>

          <!-- Sticky sidebar -->
          <aside class="post-sidebar">
            <div class="card p-5 space-y-4">
              <h3 class="post-sidebar__title">{{ $t('blog.recentPosts') }}</h3>
              <div v-if="related.length === 0" class="text-sm text-gray-400">{{ $t('blog.noPosts') }}</div>
              <div v-for="r in related" :key="r.id" class="post-related">
                <RouterLink :to="{ name: 'blog.post', params: { slug: r.slug } }" class="post-related__img-wrap">
                  <img v-if="r.cover_image" :src="r.cover_image" :alt="r.title" class="post-related__img" />
                  <div v-else class="post-related__img post-related__img--empty"></div>
                </RouterLink>
                <div class="min-w-0">
                  <RouterLink :to="{ name: 'blog.post', params: { slug: r.slug } }" class="post-related__title">
                    {{ r.title }}
                  </RouterLink>
                  <p class="post-related__date">{{ formatDate(r.published_at || r.created_at) }}</p>
                </div>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </template>
  </main>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import api from '@/api'
import VideoPlayer from '@/components/ui/VideoPlayer.vue'
import { useSeo, articleJsonLd, breadcrumbJsonLd } from '@/composables/useSeo'
import { useSettingsStore } from '@/stores/settings'

const settings = useSettingsStore()

const route   = useRoute()
const post    = ref(null)
const related = ref([])
const loading = ref(true)

const pageUrl = computed(() => window.location.href)

/* ── SEO — l'article se référence sur son titre et son extrait ── */
useSeo(() => {
  if (!post.value) return { noindex: true }

  const p = post.value
  return {
    title: p.meta_title || p.title,
    description: p.meta_description || p.excerpt || p.content,
    image: p.cover_url,
    type: 'article',
    canonical: `/blog/${p.slug}`,
    publishedAt: p.published_at,
    modifiedAt: p.updated_at,
    jsonLd: [
      articleJsonLd(p),
      breadcrumbJsonLd([
        { name: 'Accueil', path: '/' },
        { name: 'Blog', path: '/blog' },
        { name: p.title, path: `/blog/${p.slug}` },
      ]),
    ],
  }
})

async function fetchPost(slug) {
  loading.value = true
  post.value    = null
  try {
    const { data } = await api.get(`/blog/${slug}`)
    post.value = data.data ?? data
    fetchRelated()
  } catch {
    post.value = null
  } finally {
    loading.value = false
  }
}

async function fetchRelated() {
  const { data } = await api.get('/blog', { params: { page: 1 } })
  related.value = (data.data ?? [])
    .filter(p => p.slug !== route.params.slug)
    .slice(0, 3)
}

function formatDate(val) {
  if (!val) return ''
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

onMounted(() => fetchPost(route.params.slug))
watch(() => route.params.slug, (slug) => { if (slug) fetchPost(slug) })
</script>

<style scoped>
.post-page { min-height: 100vh; background: var(--color-bg); }

/* ── Loader ── */
.post-loader { display: flex; justify-content: center; padding: var(--space-20) 0; }
.post-loader__spinner {
  width: 40px; height: 40px;
  border: 2.5px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Not found ── */
.post-notfound {
  padding: var(--space-20) 0;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.post-notfound__icon { font-size: 4rem; margin-bottom: var(--space-4); }

/* ── Cover ── */
.post-cover {
  position: relative;
  height: 420px;
  overflow: hidden;
}
.post-cover__img { width: 100%; height: 100%; object-fit: cover; }
.post-cover__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 40%, rgba(33,31,31,0.55));
}

/* ── Body ── */
.post-body { padding-top: var(--space-6); padding-bottom: var(--space-20); }

/* ── Breadcrumb ── */
.post-breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--gray-400);
  margin-bottom: var(--space-8);
  flex-wrap: wrap;
}
.post-breadcrumb a:hover { color: var(--rose-500); }
.post-breadcrumb__current {
  color: var(--gray-600);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}

/* ── Layout ── */
.post-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: var(--space-10);
  align-items: start;
}
@media (max-width: 960px) {
  .post-layout { grid-template-columns: 1fr; }
  .post-sidebar { display: none; }
}

/* ── Article header ── */
.post-header { margin-bottom: var(--space-8); }
.post-header__meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  flex-wrap: wrap;
}
.post-cat-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--rose-500);
  background: var(--rose-50);
  padding: 2px 10px;
  border-radius: var(--radius-full);
}
.post-header__date { font-size: 0.8125rem; color: var(--gray-400); }
.post-header__read { font-size: 0.8125rem; color: var(--gray-400); }

.post-title {
  font-family: var(--font-display);
  font-size: clamp(1.75rem, 4vw, 2.75rem);
  font-weight: 400;
  color: var(--gray-800);
  line-height: 1.15;
  letter-spacing: -0.01em;
  margin-bottom: var(--space-4);
}
.post-excerpt {
  font-size: 1.0625rem;
  color: var(--gray-500);
  line-height: 1.65;
  border-left: 3px solid var(--rose-200);
  padding-left: var(--space-4);
  margin-bottom: var(--space-5);
}
.post-author {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding-top: var(--space-4);
  border-top: 1px solid var(--cream-200);
}
.post-author__avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-400), var(--rose-600));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  flex-shrink: 0;
}
.post-author__name { font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }
.post-author__role { font-size: 0.75rem; color: var(--gray-400); }

/* ── Footer ── */
.post-footer { margin-top: var(--space-10); padding-top: var(--space-6); }
.post-footer__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: var(--space-5);
  flex-wrap: wrap;
  gap: var(--space-4);
}
.post-back-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.875rem;
  color: var(--gray-500);
  transition: color var(--transition-fast);
}
.post-back-link:hover { color: var(--rose-500); }
.post-share {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.post-share__label { font-size: 0.8125rem; color: var(--gray-400); }
.post-share__btn {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--cream-200);
  color: var(--gray-600);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.post-share__btn:hover { background: var(--gray-800); color: #fff; }
.post-share__btn--wa:hover { background: #16a34a; color: #fff; }

/* ── Sidebar ── */
.post-sidebar { position: sticky; top: 100px; }
.post-sidebar__title {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-1);
}
.post-related {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.post-related__img-wrap { flex-shrink: 0; }
.post-related__img {
  width: 60px; height: 60px;
  border-radius: var(--radius-md);
  object-fit: cover;
  background: var(--cream-100);
  display: block;
}
.post-related__img--empty { background: var(--cream-200); }
.post-related__title {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-800);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  transition: color var(--transition-fast);
}
.post-related__title:hover { color: var(--rose-500); }
.post-related__date { font-size: 0.6875rem; color: var(--gray-400); margin-top: 2px; }
/* Vidéo de l'article — les sources sont tournées au format portrait,
   d'où le cadre centré et borné plutôt qu'une pleine largeur. */
.post-video {
  margin: var(--space-8) auto;
  max-width: 420px;
}
</style>

<!-- Prose styles for rendered HTML content -->
<style>
.post-content.prose {
  font-size: 1rem;
  line-height: 1.8;
  color: var(--gray-700);
}
.post-content.prose h2 {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  color: var(--gray-800);
  margin: 2rem 0 0.75rem;
  line-height: 1.2;
}
.post-content.prose h3 {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
  margin: 1.75rem 0 0.6rem;
}
.post-content.prose p { margin-bottom: 1.25rem; }
.post-content.prose p:last-child { margin-bottom: 0; }
.post-content.prose strong { font-weight: 600; color: var(--gray-800); }
.post-content.prose em { font-style: italic; }
.post-content.prose ul, .post-content.prose ol {
  padding-left: 1.5rem;
  margin-bottom: 1.25rem;
}
.post-content.prose li { margin-bottom: 0.375rem; }
.post-content.prose blockquote {
  border-left: 3px solid var(--rose-300);
  padding: 0.75rem 1.25rem;
  margin: 1.5rem 0;
  color: var(--gray-500);
  font-style: italic;
  background: var(--rose-50);
  border-radius: 0 var(--radius-md) var(--radius-md) 0;
}
.post-content.prose hr {
  border: none;
  border-top: 1px solid var(--cream-300);
  margin: 2rem 0;
}
.post-content.prose a {
  color: var(--rose-500);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.post-content.prose a:hover { color: var(--rose-700); }
.post-content.prose img {
  border-radius: var(--radius-md);
  margin: 1.5rem auto;
}
</style>
