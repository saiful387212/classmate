document.addEventListener('DOMContentLoaded', function () {
    const button = document.querySelector('[data-download-assignment-card]');
    const card = document.querySelector('.assignment-info-card');

    if (!button || !card) {
        return;
    }

    function wrapText(context, text, maxWidth) {
        const words = text.split(/\s+/);
        const lines = [];
        let line = '';

        words.forEach(function (word) {
            const candidate = line ? line + ' ' + word : word;
            if (line && context.measureText(candidate).width > maxWidth) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        });

        if (line) {
            lines.push(line);
        }

        return lines;
    }

    button.addEventListener('click', async function () {
        const title = document.querySelector('.page-header h1');
        const meta = document.querySelector('.assignment-meta');
        const fields = Array.from(card.querySelectorAll('.info-item')).map(function (item) {
            const label = item.querySelector('label');
            const value = item.querySelector('span');

            return {
                label: label ? label.textContent.trim() : '',
                value: value ? value.innerText.trim() : ''
            };
        }).filter(function (field) {
            return field.label && field.value;
        });

        if (!title || fields.length === 0) {
            alert('Assignment details are not available to download.');
            return;
        }

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        if (!context) {
            alert('Your browser could not create the assignment image.');
            return;
        }

        const width = 1200;
        const padding = 64;
        const contentWidth = width - padding * 2;
        const valueLines = [];
        const subtitle = meta ? meta.innerText.replace(/\s*\|\s*/g, '  •  ').trim() : '';
        let contentHeight = 250;

        fields.forEach(function (field) {
            context.font = '500 25px Arial, sans-serif';
            const lines = wrapText(context, field.value, contentWidth);
            valueLines.push(lines);
            contentHeight += 40 + Math.max(lines.length, 1) * 36 + 34;
        });

        context.font = '700 42px Arial, sans-serif';
        const titleLines = wrapText(context, title.textContent.trim(), contentWidth);
        context.font = '500 22px Arial, sans-serif';
        const subtitleLines = subtitle ? wrapText(context, subtitle, contentWidth) : [];
        contentHeight += Math.max(0, titleLines.length - 1) * 54;
        contentHeight += Math.max(0, subtitleLines.length - 1) * 34;

        canvas.width = width;
        canvas.height = contentHeight + padding;

        const background = context.createLinearGradient(0, 0, width, canvas.height);
        background.addColorStop(0, '#eef5ff');
        background.addColorStop(1, '#f8fafc');
        context.fillStyle = background;
        context.fillRect(0, 0, width, canvas.height);

        context.fillStyle = '#ffffff';
        context.beginPath();
        context.roundRect(32, 32, width - 64, canvas.height - 64, 24);
        context.fill();

        context.fillStyle = '#1769aa';
        context.beginPath();
        context.roundRect(32, 32, width - 64, 18, [24, 24, 0, 0]);
        context.fill();

        let y = 112;
        context.fillStyle = '#142b45';
        context.font = '700 42px Arial, sans-serif';
        titleLines.forEach(function (line) {
            context.fillText(line, padding, y);
            y += 54;
        });

        if (subtitleLines.length) {
            context.fillStyle = '#52677d';
            context.font = '500 22px Arial, sans-serif';
            subtitleLines.forEach(function (line) {
                context.fillText(line, padding, y);
                y += 34;
            });
        }

        y += 30;
        context.strokeStyle = '#dce6f1';
        context.lineWidth = 2;
        context.beginPath();
        context.moveTo(padding, y);
        context.lineTo(width - padding, y);
        context.stroke();
        y += 34;

        fields.forEach(function (field, index) {
            context.fillStyle = '#64748b';
            context.font = '700 18px Arial, sans-serif';
            context.fillText(field.label.toUpperCase(), padding, y);
            y += 34;

            context.fillStyle = '#1e293b';
            context.font = '500 25px Arial, sans-serif';
            valueLines[index].forEach(function (line) {
                context.fillText(line, padding, y);
                y += 36;
            });

            y += 34;
            context.strokeStyle = '#e8eef5';
            context.beginPath();
            context.moveTo(padding, y - 12);
            context.lineTo(width - padding, y - 12);
            context.stroke();
        });

        context.fillStyle = '#8392a5';
        context.font = '500 18px Arial, sans-serif';
        context.fillText('ClassMate • Assignment Details', padding, canvas.height - 58);

        try {
            const blob = await new Promise(function (resolve) {
                canvas.toBlob(resolve, 'image/png');
            });
            if (!blob) {
                throw new Error('The assignment image could not be generated.');
            }

            const imageUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = (title.textContent.trim() || 'assignment')
                .replace(/[<>:"/\\|?*\x00-\x1f]/g, '-')
                .trim() + '-assignment-card.png';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function () {
                URL.revokeObjectURL(imageUrl);
            }, 1000);
        } catch (error) {
            alert(error.message || 'The assignment image could not be downloaded.');
        }
    });
});
