(function () {

    'use strict';



    function scorePassword(pw) {

        var score = 0;

        if (pw.length >= 8) score++;

        if (pw.length >= 12) score++;

        if (/[a-z]/.test(pw)) score++;

        if (/[A-Z]/.test(pw)) score++;

        if (/[0-9]/.test(pw)) score++;

        if (/[^A-Za-z0-9]/.test(pw)) score++;

        var weak = ['password', '12345678', '123456789', 'qwerty123', 'admin123', 'simalas123'];

        if (weak.indexOf(pw.toLowerCase()) !== -1) score = 0;

        return score;

    }



    function labelForScore(score) {

        if (!score) return { text: 'Terlalu lemah', tone: 'weak', pct: 8 };

        if (score <= 2) return { text: 'Lemah', tone: 'weak', pct: 28 };

        if (score <= 4) return { text: 'Lumayan kuat', tone: 'medium', pct: 62 };

        return { text: 'Kuat', tone: 'strong', pct: 100 };

    }



    function initPasswordMeter(input) {

        var wrap = input.closest('.form-group');

        if (!wrap || wrap.querySelector('.pw-strength')) return;



        var block = document.createElement('div');

        block.className = 'pw-strength is-hidden';

        block.setAttribute('aria-hidden', 'true');



        var bar = document.createElement('div');

        bar.className = 'pw-strength-bar';

        var fill = document.createElement('div');

        fill.className = 'pw-strength-fill';

        bar.appendChild(fill);



        var label = document.createElement('div');

        label.className = 'pw-strength-label';

        label.textContent = 'Kekuatan password';



        var ul = document.createElement('ul');

        ul.className = 'pw-strength-hints';

        [

            ['len', 'Minimal 8 karakter'],

            ['lower', 'Huruf kecil (a-z)'],

            ['upper', 'Huruf besar (A-Z)'],

            ['digit', 'Angka (0-9)'],

        ].forEach(function (pair) {

            var li = document.createElement('li');

            li.setAttribute('data-rule', pair[0]);

            li.textContent = pair[1];

            ul.appendChild(li);

        });



        block.appendChild(bar);

        block.appendChild(label);

        block.appendChild(ul);

        wrap.appendChild(block);



        var hints = ul.querySelectorAll('li');

        var visible = false;



        function showMeter() {

            if (visible) return;

            visible = true;

            block.classList.remove('is-hidden');

            block.setAttribute('aria-hidden', 'false');

        }



        function paint() {

            var pw = input.value || '';



            if (!pw) {

                block.classList.add('is-hidden');

                block.setAttribute('aria-hidden', 'true');

                visible = false;

                fill.style.width = '0%';

                return;

            }



            showMeter();



            var score = scorePassword(pw);

            var meta = labelForScore(score);

            fill.style.width = meta.pct + '%';

            fill.style.background =

                meta.tone === 'strong' ? '#16a34a' : meta.tone === 'medium' ? '#d97706' : '#dc2626';

            label.textContent = 'Kekuatan password: ' + meta.text;



            hints.forEach(function (li) {

                var rule = li.getAttribute('data-rule');

                var ok = false;

                if (rule === 'len') ok = pw.length >= 8;

                if (rule === 'lower') ok = /[a-z]/.test(pw);

                if (rule === 'upper') ok = /[A-Z]/.test(pw);

                if (rule === 'digit') ok = /[0-9]/.test(pw);

                li.classList.toggle('ok', ok);

            });

        }



        input.addEventListener('input', paint);

    }



    function initEmailCheck(input) {

        var wrap = input.closest('.form-group');

        if (!wrap || wrap.querySelector('.email-hint')) return;



        var hint = document.createElement('div');

        hint.className = 'email-hint is-hidden';

        hint.textContent = 'Gunakan email institusi atau email aktif yang bisa menerima OTP.';

        wrap.appendChild(hint);



        function paint() {

            var v = (input.value || '').trim();

            if (!v) {

                hint.classList.add('is-hidden');

                return;

            }

            hint.classList.remove('is-hidden');

            var ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);

            hint.className = 'email-hint ' + (ok ? 'valid' : 'invalid');

            hint.textContent = ok

                ? 'Format email valid. Verifikasi OTP akan dikirim ke alamat ini.'

                : 'Format email belum valid (contoh: nama@polibatam.ac.id).';

        }



        input.addEventListener('input', paint);

    }



    document.addEventListener('DOMContentLoaded', function () {

        var regPw = document.querySelector('#panelRegister input[name="password"]');

        if (regPw) initPasswordMeter(regPw);



        var regEmail = document.querySelector('#panelRegister input[name="email"]');

        if (regEmail) initEmailCheck(regEmail);



        var forgotEmail = document.querySelector('#panelForgot input[name="email"]');

        if (forgotEmail) initEmailCheck(forgotEmail);

    });

})();

