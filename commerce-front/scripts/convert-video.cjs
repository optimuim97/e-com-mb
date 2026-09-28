/**
 * Convertit public/V6.mov → public/V6.mp4 (H.264, AAC)
 * Usage : node scripts/convert-video.cjs
 */

const ffmpegInstaller = require('@ffmpeg-installer/ffmpeg')
const ffmpeg          = require('fluent-ffmpeg')
const path            = require('path')

ffmpeg.setFfmpegPath(ffmpegInstaller.path)

const root  = path.join(__dirname, '..')
const input  = path.join(root, 'public', 'V6.mov')
const output = path.join(root, 'public', 'V6.mp4')

console.log('🎬  Conversion en cours…')
console.log('    Entrée  :', input)
console.log('    Sortie  :', output)

ffmpeg(input)
  .videoCodec('libx264')
  .audioCodec('aac')
  .outputOptions([
    '-crf 22',          // qualité (0=parfaite, 51=pire) — 22 bon compromis
    '-preset fast',     // vitesse encodage
    '-movflags +faststart', // lecture progressive (indispensable pour le web)
    '-pix_fmt yuv420p', // compatibilité max navigateurs
  ])
  .on('progress', ({ percent }) => {
    if (percent) process.stdout.write(`\r    ${Math.round(percent)}%`)
  })
  .on('end', () => {
    console.log('\n✅  V6.mp4 créé dans /public')
    process.exit(0)
  })
  .on('error', (err) => {
    console.error('\n❌  Erreur :', err.message)
    process.exit(1)
  })
  .save(output)
