import { readFileSync, writeFileSync } from 'node:fs';
import vm from 'node:vm';

const lines = readFileSync(process.argv[2], 'utf8').split(/\r?\n/);
const source = `${lines.slice(0, 13).join('\n')}\n${lines.slice(30, 43).join('\n')}\nJSON.stringify(products.map(p => {
  const details = productDetails[p[0]];
  return {
    source_id: p[0], name: p[1], category: p[2], inr_price: p[3],
    description: details[0], facts: details[1],
    image_urls: p[0] === 'copper-kit' ? [] : [p[5], ...details[2].map(file => 'https://www.muzzo.store/public/storage/products/' + file)]
  };
}))`;
writeFileSync(process.argv[3], JSON.stringify(JSON.parse(vm.runInNewContext(source, {}, { timeout: 1000 })), null, 2) + '\n');
