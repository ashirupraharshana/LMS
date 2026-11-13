document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="file"][data-image-preview]').forEach(input => {
    input.addEventListener('change', () => {
      const target = document.getElementById(input.dataset.imagePreview);
      if (!target) return;
      const file = input.files?.[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 1048576) {
        input.value = '';
        target.textContent = 'Choose a JPG, PNG or WebP image under 1 MB.';
        return;
      }
      const url = URL.createObjectURL(file);
      target.replaceChildren();
      const img = document.createElement('img');
      img.alt = 'Selected image preview';
      img.onload = () => URL.revokeObjectURL(url);
      img.onerror = () => URL.revokeObjectURL(url);
      img.src = url;
      target.appendChild(img);
    });
  });
});
