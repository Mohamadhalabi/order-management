<div
    x-data="{
        calc() {
            let sub = 0;

            document.querySelectorAll('input[id*=\'.items.\'][id$=\'.unit_price\']').forEach(priceEl => {
                const qtyEl = document.getElementById(priceEl.id.replace('.unit_price', '.qty'));
                const qty   = parseFloat(qtyEl?.value)   || 0;
                const price = parseFloat(priceEl?.value) || 0;
                sub += qty * price;
            });

            sub = Math.round(sub * 100) / 100;

            const g = (suffix) => parseFloat(document.querySelector('input[id$=\'.' + suffix + '\']')?.value) || 0;

            const dp  = g('discount_percent');
            const da  = g('discount_amount');
            const kp  = g('kdv_percent');
            const sh  = g('shipping_amount');
            const pd  = Math.round(sub * dp / 100 * 100) / 100;
            const fd  = Math.min(Math.max(da, pd), sub);
            const base = Math.max(sub - fd, 0);
            const kdv  = Math.round(base * kp / 100 * 100) / 100;
            const total = Math.round((base + sh + kdv) * 100) / 100;

            const set = (suffix, val) => {
                const el = document.querySelector('input[id$=\'.' + suffix + '\']');
                if (el) el.value = val.toFixed(2);
            };

            set('subtotal',   sub);
            set('kdv_amount', kdv);
            set('total',      total);
        },

        wire() {
            const selectors = [
                'input[id*=\'.items.\'][id$=\'.qty\']',
                'input[id*=\'.items.\'][id$=\'.unit_price\']',
                'input[id$=\'.discount_percent\']',
                'input[id$=\'.discount_amount\']',
                'input[id$=\'.kdv_percent\']',
                'input[id$=\'.shipping_amount\']',
            ];
            selectors.forEach(sel => {
                document.querySelectorAll(sel).forEach(el => {
                    if (el._oc) return;
                    el._oc = true;
                    el.addEventListener('input',  () => this.calc());
                    el.addEventListener('change', () => this.calc());
                });
            });
            this.calc();
        }
    }"
    x-init="
        wire();
        setInterval(() => wire(), 800);

        window.addEventListener('livewire:update', () => {
            setTimeout(() => wire(), 100);
        });
    "
></div>