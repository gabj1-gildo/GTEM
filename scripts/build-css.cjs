const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');
const source = process.env.GTEM_NODE_MODULES ? createRequire(path.join(process.env.GTEM_NODE_MODULES, '../package.json')) : require;
const postcss = source('postcss');
const tailwind = source('@tailwindcss/postcss');
const root = path.resolve(__dirname, '..');
const from = path.join(root, 'resources/css/app.css');
postcss([tailwind({base: root})]).process(fs.readFileSync(from, 'utf8'), {from, to:path.join(root,'public/app.css')}).then(result => {
    fs.writeFileSync(path.join(root,'public/app.css'), result.css);
    console.log('CSS gerado em public/app.css');
}).catch(error => { console.error(error); process.exit(1); });
