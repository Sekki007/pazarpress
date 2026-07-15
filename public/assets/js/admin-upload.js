(function () {
  const input = document.getElementById("coverUpload");
  const hidden = document.getElementById("coverImage");
  const preview = document.getElementById("coverPreview");
  const status = document.getElementById("coverUploadStatus");
  if (!input || !hidden) return;

  function setPreview(url) {
    if (!preview) return;
    preview.classList.remove("cover-preview--empty");
    preview.innerHTML = '<img src="' + url + '" alt="Pregled cover slike">';
  }

  function setStatus(msg, isError) {
    if (!status) return;
    status.textContent = msg;
    status.className = "cover-upload-status" + (isError ? " cover-upload-status--err" : " cover-upload-status--ok");
  }

  input.addEventListener("change", async function () {
    const file = input.files && input.files[0];
    if (!file) return;

    setStatus("Uploadujem…", false);

    try {
      const fd = new FormData();
      fd.append("file", file, file.name);

      const res = await fetch("/admin/upload", { method: "POST", body: fd });
      const data = await res.json();
      if (!res.ok || !data.url) {
        setStatus(data.error || "Upload nije uspio.", true);
        return;
      }
      hidden.value = data.url;
      setPreview(data.url);
      setStatus("Slika uploadovana — klikni Sačuvaj.", false);
    } catch (e) {
      setStatus(e.message || "Greška pri uploadu.", true);
    }
  });
})();
