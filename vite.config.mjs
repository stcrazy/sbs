import {defineConfig} from 'vite';
export default defineConfig({base:'/sbs/',build:{outDir:'.build',emptyOutDir:true},server:{proxy:{'/sbs/api':{target:'http://127.0.0.1:8091',changeOrigin:false}}}});
