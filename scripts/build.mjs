import {build} from 'vite';
import {cpSync,mkdirSync,readdirSync,unlinkSync} from 'node:fs';
import path from 'node:path';
await build({build:{rollupOptions:{input:'app.html'}}});
mkdirSync('assets',{recursive:true});
const root=path.resolve('assets');
for(const name of readdirSync(root)){const file=path.resolve(root,name);if(path.dirname(file)!==root)throw Error('Invalid asset path');if(/^[\w.-]+\.(?:js|css)$/.test(name))unlinkSync(file);}
cpSync('.build/assets','assets',{recursive:true});
cpSync('.build/app.html','index.html');
console.log('Deployable PHP site built at /sbs.');
