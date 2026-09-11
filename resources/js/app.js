import '../css/app.css';
import { createIcons, Wallet, Users, Package, LayoutDashboard, Plus, ArrowRight, Search, LogOut, ShieldCheck, ChevronLeft, UserRound, Check } from 'lucide';
createIcons({ icons: { Wallet, Users, Package, LayoutDashboard, Plus, ArrowRight, Search, LogOut, ShieldCheck, ChevronLeft, UserRound, Check } });
for (const form of document.querySelectorAll('form[method="post"]')) {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    if (button) {button.disabled = true; button.setAttribute('aria-busy','true');}
  });
}
