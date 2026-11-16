import hashlib
import io
import json
import sys
import urllib.request
import zipfile
from pathlib import Path
from urllib.parse import urlparse

from PIL import Image


manifest = Path('backend/catalog/muzzo-products.json')
media = Path('backend/catalog/media')
products = json.loads(manifest.read_text(encoding='utf-8'))
archive = zipfile.ZipFile(sys.argv[1])
bottle = [name for name in archive.namelist() if name.lower().endswith(('.jpg', '.jpeg', '.png'))]
if len(bottle) != 17:
    raise RuntimeError(f'Expected 17 supplied bottle photos, found {len(bottle)}')


def save_image(data, target):
    if len(data) > 8_000_000:
        raise RuntimeError(f'Image exceeds 8 MB: {target}')
    image = Image.open(io.BytesIO(data))
    image.verify()
    image = Image.open(io.BytesIO(data))
    if min(image.size) < 200 or image.format not in ('JPEG', 'PNG', 'WEBP'):
        raise RuntimeError(f'Invalid product image: {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(data)
    return hashlib.sha256(data).hexdigest()


for product in products:
    source_id = product['source_id']
    image_urls = product.pop('image_urls')
    product['images'] = []
    seen = set()
    sources = [(name, archive.read(name)) for name in bottle] if source_id == 'copper-kit' else []
    if source_id != 'copper-kit':
        for url in image_urls:
            if urlparse(url).hostname != 'www.muzzo.store':
                raise RuntimeError(f'Unexpected image host: {url}')
            try:
                with urllib.request.urlopen(url, timeout=20) as response:
                    if not response.headers.get('Content-Type', '').startswith('image/'):
                        raise RuntimeError(f'Not an image: {url}')
                    sources.append((url, response.read(8_000_001)))
            except Exception as error:
                print(f'Skipped {source_id} image: {error}', file=sys.stderr)
    for index, (source, data) in enumerate(sources, 1):
        image_format = Image.open(io.BytesIO(data)).format
        extension = {'JPEG': '.jpg', 'PNG': '.png', 'WEBP': '.webp'}.get(image_format, '.bin')
        target = media / source_id / f'{index:02d}{extension}'
        try:
            digest = save_image(data, target)
        except Exception as error:
            print(f'Skipped {source_id} image: {error}', file=sys.stderr)
            continue
        if digest in seen:
            target.unlink()
            continue
        seen.add(digest)
        product['images'].append(str(target.relative_to(manifest.parent)).replace('\\', '/'))
    print(f'{source_id}: {len(product["images"])} images')

manifest.write_text(json.dumps(products, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
