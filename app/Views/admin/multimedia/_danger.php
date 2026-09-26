<?php if ($row !== null && can($module . '.delete')): ?>
  <div class="danger-zone mt-3">
    <div><b>हटाएँ</b><p class="mb-0 small">यह हमेशा के लिए हट जाएगा। मीडिया लाइब्रेरी की फ़ाइलें बनी रहेंगी।</p></div>
    <?= delete_button(route('admin.' . $module . '.destroy', ['id' => $row['id']]), '“' . $row['title'] . '” हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?>
  </div>
<?php endif; ?>
