(function () {
  const textarea = document.getElementById("article-body");
  if (!textarea || typeof tinymce === "undefined") return;

  function imageExtFromType(type) {
    if (!type) return "jpg";
    if (type.indexOf("png") !== -1) return "png";
    if (type.indexOf("webp") !== -1) return "webp";
    if (type.indexOf("gif") !== -1) return "gif";
    return "jpg";
  }

  function ensureImageFilename(blob, filename) {
    var type = (blob && blob.type) || "image/jpeg";
    var ext = imageExtFromType(type);
    var name = filename || (blob instanceof File && blob.name) || "slika." + ext;
    if (!/\.(jpe?g|png|webp|gif)$/i.test(name)) {
      name = String(name).replace(/\.[^.]+$/, "") + "." + ext;
      if (!/\.(jpe?g|png|webp|gif)$/i.test(name)) {
        name = "slika." + ext;
      }
    }
    return name;
  }

  function uploadImage(blob, filename, progress) {
    return new Promise(async function (resolve, reject) {
      try {
        var safeName = ensureImageFilename(blob, filename);
        var type = (blob && blob.type) || "image/jpeg";
        var file = blob instanceof File
          ? new File([blob], safeName, { type: blob.type || type })
          : new File([blob], safeName, { type: type });
        var fd = new FormData();
        fd.append("file", file, safeName);
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
