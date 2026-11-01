import {createRoot} from 'react-dom/client';
import Games from './games';
import './club.css';
createRoot(document.getElementById('root')!).render(<Games/>);

if('serviceWorker'in navigator)navigator.serviceWorker.register('/sbs/sw.js',{scope:'/sbs/'}).catch(()=>{});
