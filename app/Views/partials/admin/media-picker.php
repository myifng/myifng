<?php
/** मीडिया पिकर: [data-media-pick] बटन या एडिटर का "लाइब्रेरी" बटन इसे खोलता है (admin.js) */
use App\Services\MediaService;
?>
<div class="modal fade" id="mediaPicker" tabindex="-1" aria-labelledby="mediaPickerTitle" aria-hidden="true"
     data-browse="<?= e(route('admin.media.browse')) ?>" data-upload="<?= can('media.create') ? e(route('admin.media.store')) : '' ?>">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
    <div class="modal-content">
      <div class="modal-header gap-2 flex-wrap">
        <h2 class="modal-title h5 me-auto" id="mediaPickerTitle"><i class="fa-solid fa-photo-film me-2 text-body-secondary"></i>मीडिया लाइब्रेरी से चुनें</h2>
        <div class="input-icon mp-search"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control form-control-sm" type="search" placeholder="खोजें…" aria-label="मीडिया खोजें" data-mp-search></div>
        <?php if (can('media.create')): ?><label class="btn btn-sm btn-dark mb-0"><i class="fa-solid fa-cloud-arrow-up me-1"></i> नई अपलोड<input type="file" hidden multiple accept="<?= e(MediaService::accept('image')) ?>" data-mp-upload></label><?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="बंद करें"></button>
      </div>
      <div class="modal-body p-0">
        <div class="mp-body">
          <div class="mp-main">
            <ul class="media-grid mp-grid" data-mp-grid role="listbox" aria-label="फ़ाइलें"></ul>
            <div class="text-center p-3" data-mp-more-wrap hidden><button class="btn btn-sm btn-outline-secondary" type="button" data-mp-more>और दिखाएँ</button></div>
            <div class="empty-state" data-mp-empty hidden><i class="fa-solid fa-photo-film"></i><p>कुछ नहीं मिला।</p></div>
          </div>
          <aside class="mp-side" data-mp-side hidden>
            <img alt="" data-mp-prev>
            <b class="d-block mt-2" data-mp-name></b>
            <span class="small text-body-secondary d-block mb-2" data-mp-info></span>
            <label class="form-label small" for="mpAlt">Alt टेक्स्ट</label>
            <input class="form-control form-control-sm mb-2" id="mpAlt" maxlength="255" data-mp-alt>
            <label class="form-label small" for="mpCaption">कैप्शन</label>
            <input class="form-control form-control-sm mb-2" id="mpCaption" maxlength="500" data-mp-caption>
            <p class="small text-body-secondary mb-0">यहाँ बदला alt/कैप्शन सिर्फ़ इस जगह के लिए है। लाइब्रेरी में स्थायी बदलाव के लिए फ़ाइल खोलें।</p>
          </aside>
        </div>
      </div>
      <div class="modal-footer">
        <span class="small text-body-secondary me-auto" data-mp-status></span>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">रद्द करें</button>
        <button type="button" class="btn btn-brand" data-mp-choose disabled>चुनें</button>
      </div>
    </div>
  </div>
</div>
