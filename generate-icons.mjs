import sharp from 'sharp'

const src = 'public/images/primegest.png'

for (const size of [192, 512]) {
  await sharp(src)
    .resize(size, size, { fit: 'contain', background: { r: 17, g: 24, b: 39, alpha: 1 } })
    .png()
    .toFile(`public/icons/icon-${size}x${size}.png`)
  console.log(`✅ icon-${size}x${size}.png`)
}

await sharp(src)
  .resize(180, 180, { fit: 'contain', background: { r: 17, g: 24, b: 39, alpha: 1 } })
  .png()
  .toFile('public/icons/apple-touch-icon.png')
console.log('✅ apple-touch-icon.png')

await sharp(src)
  .resize(32, 32, { fit: 'contain', background: { r: 17, g: 24, b: 39, alpha: 1 } })
  .png()
  .toFile('public/favicon-32.png')
console.log('✅ favicon-32.png')
