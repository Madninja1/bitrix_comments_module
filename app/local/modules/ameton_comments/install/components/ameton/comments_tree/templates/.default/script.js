(function () {
    if (window.__amcBound) return;
    window.__amcBound = true;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.amc__togglebtn');
        if (!btn) return;

        const parentId = btn.dataset.id;
        const block = document.querySelector(`.amc__children[data-parent="${parentId}"]`);
        if (!block) return;

        const hidden = block.classList.toggle('amc__children--hidden');
        btn.textContent = hidden
            ? btn.textContent.replace('Скрыть', 'Показать')
            : btn.textContent.replace('Показать', 'Скрыть');
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.amc__replybtn');
        if (!btn) return;

        if (btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';
        btn.disabled = true;

        const parentId = parseInt(btn.dataset.id, 10);
        const level = parseInt(btn.dataset.nextLevel, 10);

        const container = document.querySelector(`.amc__lazy[data-container="${parentId}"]`);
        if (!container) {
            btn.dataset.loading = '0';
            btn.disabled = false;
            return;
        }

        const cursorAt = btn.dataset.cAt || null;
        const cursorId = btn.dataset.cId ? parseInt(btn.dataset.cId, 10) : null;

        try {
            const resp = await BX.ajax.runComponentAction(
                'ameton:comments_tree',
                'loadChildren',
                {
                    mode: 'class',
                    data: { parentId, level, cursorAt, cursorId }
                }
            );

            if (!resp || !resp.data || !resp.data.ok) {
                throw new Error((resp && resp.data && resp.data.error) ? resp.data.error : 'ajax_error');
            }

            const data = resp.data;

            if (data.html) {
                const existing = new Set(
                    Array.from(container.querySelectorAll('.amc__item[data-id]'))
                        .map(el => el.getAttribute('data-id'))
                );

                const tmp = document.createElement('div');
                tmp.innerHTML = data.html;

                Array.from(tmp.querySelectorAll('.amc__item[data-id]')).forEach((node) => {
                    const id = node.getAttribute('data-id');
                    if (!existing.has(id)) {
                        existing.add(id);
                        container.appendChild(node);
                    }
                });
            }

            if (data.nextCursor && data.nextCursor.c_at && data.nextCursor.c_id) {
                btn.dataset.cAt = data.nextCursor.c_at;
                btn.dataset.cId = String(data.nextCursor.c_id);
            }

            if (!data.hasMore) {
                btn.remove();
                return;
            }

            btn.disabled = false;
            btn.dataset.loading = '0';
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.dataset.loading = '0';
            alert('Ошибка загрузки ответов');
        }
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.amc__loadmore-roots');
        if (!btn) return;

        if (btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';
        btn.disabled = true;

        const list = document.querySelector('.amc__list');
        if (!list) return;

        const newsId = parseInt(btn.dataset.newsId, 10);
        const cursorTs = btn.dataset.cTs ? parseInt(btn.dataset.cTs, 10) : null;
        const cursorId = btn.dataset.cId ? parseInt(btn.dataset.cId, 10) : null;

        try {
            const resp = await BX.ajax.runComponentAction(
                'ameton:comments_tree',
                'loadRoots',
                {
                    mode: 'class',
                    data: { newsId, cursorTs, cursorId }
                }
            );

            if (!resp || !resp.data || !resp.data.ok) {
                throw new Error((resp && resp.data && resp.data.error) ? resp.data.error : 'ajax_error');
            }

            const data = resp.data;

            if (data.html) {
                list.insertAdjacentHTML('beforeend', data.html);
            }

            if (data.nextCursor && data.nextCursor.c_ts && data.nextCursor.c_id) {
                btn.dataset.cTs = String(data.nextCursor.c_ts);
                btn.dataset.cId = String(data.nextCursor.c_id);
            }

            if (!data.hasMore) {
                btn.remove();
                return;
            }

            btn.disabled = false;
            btn.dataset.loading = '0';
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.dataset.loading = '0';
            alert('Ошибка загрузки комментариев');
        }
    });
})();

