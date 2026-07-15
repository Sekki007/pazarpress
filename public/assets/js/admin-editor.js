(function () {
  const textarea = document.getElementById("article-body");
  if (!textarea || typeof tinymce === "undefined") return;

  function uploadImage(blob, filename, progress) {
    return new Promise(async function (resolve, reject) {
      try {
        var file = blob instanceof File ? blob : new File([blob], filename || "slika.jpg", { type: blob.type || "image/jpeg" });
        var fd = new FormData();
        fd.append("file", file, file.name);
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "/admin/upload");
        xhr.upload.onprogress = function (e) {
          if (e.lengthComputable) progress((e.loaded / e.total) * 100);
        };
        xhr.onload = function () {
          if (xhr.status < 200 || xhr.status >= 300) {
            reject("Upload nije uspio (HTTP " + xhr.status + ")");
            return;
          }
          try {
            var json = JSON.parse(xhr.responseText);
            if (json.url) resolve(json.url);
            else reject(json.error || "Upload nije uspio.");
          } catch (err) {
            reject("Nevaljan odgovor servera.");
          }
        };
        xhr.onerror = function () {
          reject("Greška pri uploadu slike.");
        };
        xhr.send(fd);
      } catch (err) {
        reject(err.message || "Greška pri obradi slike.");
      }
    });
  }

  tinymce.init({
    selector: "#article-body",
    license_key: "gpl",
    base_url: "https://cdn.jsdelivr.net/npm/tinymce@7.6.1",
    suffix: ".min",
    height: 480,
    menubar: "edit insert format view table tools",
    branding: false,
    promotion: false,
    plugins: [
      "advlist", "autolink", "lists", "link", "image", "charmap",
      "preview", "anchor", "searchreplace", "visualblocks", "code",
      "fullscreen", "insertdatetime", "media", "table", "wordcount",
    ],
    toolbar:
      "undo redo | blocks fontsize | bold italic underline strikethrough | " +
      "alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | " +
      "link image table blockquote hr | removeformat | code preview fullscreen",
    block_formats: "Pasus=p; Naslov 2=h2; Naslov 3=h3; Naslov 4=h4; Citat=blockquote",
    image_caption: true,
    image_title: true,
    automatic_uploads: true,
    file_picker_types: "image",
    images_upload_handler: function (blobInfo, progress) {
      return uploadImage(blobInfo.blob(), blobInfo.filename(), progress);
    },
    content_style:
      "body { font-family: Inter, system-ui, sans-serif; font-size: 16px; line-height: 1.6; color: #171d1b; }",
  });
})();
