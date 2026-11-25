/**
 * LaporKota — Civic Complaint Platform
 * Purwokerto, Banyumas, Jawa Tengah
 * Main Application JavaScript (Vanilla JS)
 */

(function () {
  'use strict';

  // Base URL helper matching PHP base_url()
  const BASE_URL = '/aduan-kota/';

  /**
   * Helper to construct absolute URLs within the application
   */
  function appUrl(path) {
    const cleanPath = (path || '').replace(/^\/+/, '');
    return BASE_URL + cleanPath;
  }

  /**
   * 1. CSRF Token Reader
   * Reads from meta tag <meta name="csrf-token" content="..."> or hidden input
   */
  function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag && metaTag.getAttribute('content')) {
      return metaTag.getAttribute('content');
    }
    const inputToken = document.querySelector('input[name="csrf_token"]');
    if (inputToken) {
      return inputToken.value;
    }
    return '';
  }

  /**
   * 2. Toast Notification System
   * Creates toast notifications (success / error / info / warning)
   */
  function showToast(message, type = 'info', title = null) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const toastType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    toast.className = `toast-custom toast-${toastType}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    let iconName = 'info';
    let defaultTitle = 'Pemberitahuan';
    if (toastType === 'success') {
      iconName = 'check_circle';
      defaultTitle = 'Berhasil';
    } else if (toastType === 'error') {
      iconName = 'error';
      defaultTitle = 'Terjadi Kesalahan';
    } else if (toastType === 'warning') {
      iconName = 'warning';
      defaultTitle = 'Peringatan';
    }

    const finalTitle = title !== null ? title : defaultTitle;

    toast.innerHTML = `
      <div class="toast-icon-box">
        <span class="material-symbols-outlined">${iconName}</span>
      </div>
      <div class="toast-content">
        <div class="toast-title">${escapeHtml(finalTitle)}</div>
        <p class="toast-message">${escapeHtml(message)}</p>
      </div>
      <button type="button" class="toast-close-btn" aria-label="Tutup">
        <span class="material-symbols-outlined md-18">close</span>
      </button>
    `;

    const closeBtn = toast.querySelector('.toast-close-btn');
    const dismiss = () => {
      toast.classList.add('toast-hiding');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    };

    if (closeBtn) {
      closeBtn.addEventListener('click', dismiss);
    }

    container.appendChild(toast);

    // Auto dismiss after 4.5 seconds
    setTimeout(dismiss, 4500);
  }

  /**
   * Escape HTML utility to prevent XSS in dynamic elements
   */
  function escapeHtml(str) {
    if (typeof str !== 'string') return str;
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Format relative time in Indonesian
   */
  function timeAgo(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const diffInSeconds = Math.floor((now - past) / 1000);

    if (isNaN(diffInSeconds) || diffInSeconds < 60) return 'Baru saja';
    const minutes = Math.floor(diffInSeconds / 60);
    if (minutes < 60) return `${minutes} menit yang lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam yang lalu`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days} hari yang lalu`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months} bulan yang lalu`;
    const years = Math.floor(months / 12);
    return `${years} tahun yang lalu`;
  }

  /**
   * 3. Auto-dismiss Flash Messages
   * Dismisses inline alerts after 5 seconds
   */
  function initFlashMessages() {
    const alerts = document.querySelectorAll('.alert-dismissible, .alert-custom, .flash-message');
    alerts.forEach((alert) => {
      // Allow opting out of auto-dismiss via data-no-autodismiss
      if (alert.hasAttribute('data-no-autodismiss')) return;

      setTimeout(() => {
        alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 500);
      }, 5000);
    });
  }

  /**
   * 4. File Upload Preview & Drag-and-Drop
   * Handles input change, drag & drop, file validation (max 2MB, allowed images)
   */
  function initFilePreview(inputId, previewId, options = {}) {
    const input = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewId);
    if (!input || !previewContainer) return;

    const maxSizeBytes = (options.maxSizeMb || 2) * 1024 * 1024;
    const allowedTypes = options.allowedTypes || ['image/jpeg', 'image/png', 'image/webp'];
    const dropzone = options.dropzoneId ? document.getElementById(options.dropzoneId) : input.closest('.dropzone-upload');

    function handleFile(file) {
      if (!file) return;

      // Validation
      if (!allowedTypes.includes(file.type)) {
        showToast('Format file tidak didukung. Harap pilih gambar JPG, PNG, atau WebP.', 'error');
        input.value = '';
        return;
      }

      if (file.size > maxSizeBytes) {
        showToast(`Ukuran file terlalu besar. Maksimal ${options.maxSizeMb || 2} MB.`, 'error');
        input.value = '';
        return;
      }

      // Read file and set preview
      const reader = new FileReader();
      reader.onload = function (e) {
        let previewImg = previewContainer.querySelector('img');
        if (!previewImg) {
          previewContainer.innerHTML = `
            <img src="${e.target.result}" alt="Preview Foto Pengaduan">
            <button type="button" class="img-preview-remove" title="Hapus foto">
              <span class="material-symbols-outlined md-18">close</span>
            </button>
            <div class="p-2 bg-white text-muted small text-truncate file-info-text">${escapeHtml(file.name)} (${(file.size / 1024).toFixed(0)} KB)</div>
          `;
          const removeBtn = previewContainer.querySelector('.img-preview-remove');
          if (removeBtn) {
            removeBtn.addEventListener('click', clearPreview);
          }
        } else {
          previewImg.src = e.target.result;
          const infoText = previewContainer.querySelector('.file-info-text');
          if (infoText) {
            infoText.textContent = `${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
          }
        }

        previewContainer.classList.add('show');
        previewContainer.style.display = 'block';

        if (dropzone) {
          const dropzoneTitle = dropzone.querySelector('.dropzone-title');
          if (dropzoneTitle) {
            dropzoneTitle.textContent = 'Klik atau seret untuk mengganti foto';
          }
        }
      };

      reader.readAsDataURL(file);
    }

    function clearPreview() {
      input.value = '';
      previewContainer.innerHTML = '';
      previewContainer.classList.remove('show');
      previewContainer.style.display = 'none';
      if (dropzone) {
        const dropzoneTitle = dropzone.querySelector('.dropzone-title');
        if (dropzoneTitle) {
          dropzoneTitle.textContent = 'Seret & lepas foto pengaduan di sini';
        }
      }
    }

    input.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        handleFile(this.files[0]);
      }
    });

    // Drag and drop events if dropzone exists
    if (dropzone) {
      // Trigger native file dialog on dropzone click (if not a <label>)
      if (dropzone.tagName.toLowerCase() !== 'label') {
        dropzone.addEventListener('click', function (e) {
          if (e.target !== input) {
            input.click();
          }
        });
      }

      // Keyboard accessibility
      if (!dropzone.hasAttribute('tabindex')) {
        dropzone.setAttribute('tabindex', '0');
      }
      dropzone.setAttribute('role', 'button');
      dropzone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          input.click();
        }
      });

      ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, function (e) {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.add('dragover');
        });
      });

      ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, function (e) {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.remove('dragover');
        });
      });

      dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
          const file = e.dataTransfer.files[0];
          input.files = e.dataTransfer.files;
          handleFile(file);
        }
      });
    }

    return { clear: clearPreview };
  }

  /**
   * 5. Leaflet Map Helper
   * Initializes a Leaflet map for viewing or coordinate picking
   */
  function initLeafletMap(containerId, options = {}) {
    if (typeof L === 'undefined') {
      console.warn('Leaflet library (L) is not loaded.');
      return null;
    }

    const container = document.getElementById(containerId);
    if (!container) return null;

    // Purwokerto center coordinate defaults
    const defaultLat = options.latitude || -7.4243;
    const defaultLng = options.longitude || 109.2302;
    const defaultZoom = options.zoom || 13;

    const map = L.map(containerId, {
      zoomControl: true,
      scrollWheelZoom: options.scrollWheelZoom !== false
    }).setView([defaultLat, defaultLng], defaultZoom);

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(map);

    let draggableMarker = null;

    // Coordinate picker mode (used on complaint submission / edit)
    if (options.picker) {
      const latInput = options.latInputId ? document.getElementById(options.latInputId) : null;
      const lngInput = options.lngInputId ? document.getElementById(options.lngInputId) : null;
      const addressInput = options.addressInputId ? document.getElementById(options.addressInputId) : null;
      const addressStatus = options.addressStatusId ? document.getElementById(options.addressStatusId) : null;

      let geocodeAbortController = null;

      const markerPos = [
        latInput && latInput.value ? parseFloat(latInput.value) : defaultLat,
        lngInput && lngInput.value ? parseFloat(lngInput.value) : defaultLng
      ];

      draggableMarker = L.marker(markerPos, {
        draggable: true,
        autoPan: true
      }).addTo(map);

      // Function to reverse geocode coordinates into a real-world address
      const reverseGeocode = (lat, lng) => {
        if (!addressInput) return;

        if (geocodeAbortController) {
          geocodeAbortController.abort();
        }
        geocodeAbortController = new AbortController();
        const signal = geocodeAbortController.signal;

        if (addressStatus) {
          addressStatus.style.display = 'inline-flex';
          addressStatus.innerHTML = `
            <span class="material-symbols-outlined spin-animation" style="font-size: 16px; color: #0F766E;">sync</span>
            <span style="color: #0F766E; font-size: 0.825rem;">Mencari alamat lokasi dari peta...</span>
          `;
        }

        const internalApiUrl = appUrl(`api/reverse_geocode.php?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);
        const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&accept-language=id`;

        const applyAddress = (address) => {
          if (!address) return;
          addressInput.value = address;
          addressInput.classList.remove('is-invalid');
          addressInput.dispatchEvent(new Event('input', { bubbles: true }));
          addressInput.dispatchEvent(new Event('change', { bubbles: true }));

          if (draggableMarker) {
            draggableMarker.bindPopup(`<b>Titik Pengaduan</b><br><span style="font-size:0.85rem;">${escapeHtml(address)}</span><br><small style="color:#64748b;">Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}</small>`).openPopup();
          }

          if (addressStatus) {
            addressStatus.style.display = 'inline-flex';
            addressStatus.innerHTML = `
              <span class="material-symbols-outlined" style="color: #16A34A; font-size: 16px;">check_circle</span>
              <span style="color: #16A34A; font-size: 0.825rem;">Alamat otomatis terisi dari titik peta</span>
            `;
            setTimeout(() => {
              if (addressStatus.textContent.includes('terisi')) {
                addressStatus.style.display = 'none';
              }
            }, 3500);
          }
        };

        const tryFetch = async () => {
          try {
            // First try internal API (avoids CORS and adheres to User-Agent policy)
            const res = await fetch(internalApiUrl, { signal });
            if (res.ok) {
              const data = await res.json();
              if (data && data.success && data.address) {
                applyAddress(data.address);
                return;
              }
            }
            throw new Error('Internal API geocode fallback needed');
          } catch (err) {
            if (err.name === 'AbortError') return;

            // Direct Nominatim fallback
            try {
              const directRes = await fetch(nominatimUrl, { signal });
              if (directRes.ok) {
                const directData = await directRes.json();
                if (directData && directData.display_name) {
                  applyAddress(directData.display_name);
                  return;
                }
              }
            } catch (directErr) {
              if (directErr.name === 'AbortError') return;
            }

            if (addressStatus) {
              addressStatus.style.display = 'inline-flex';
              addressStatus.innerHTML = `
                <span class="material-symbols-outlined" style="color: #64748b; font-size: 16px;">info</span>
                <span style="color: #64748b; font-size: 0.825rem;">Alamat belum terdeteksi otomatis. Silakan ketik manual.</span>
              `;
            }
          }
        };

        tryFetch();
      };

      const updateInputs = (lat, lng, shouldGeocode = true) => {
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        draggableMarker.bindPopup(`<b>Titik Pengaduan</b><br>Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}`).openPopup();
        if (shouldGeocode) {
          reverseGeocode(lat, lng);
        }
      };

      // Set initial values if empty
      if (latInput && !latInput.value) latInput.value = markerPos[0].toFixed(6);
      if (lngInput && !lngInput.value) lngInput.value = markerPos[1].toFixed(6);

      draggableMarker.on('dragend', function (e) {
        const pos = e.target.getLatLng();
        updateInputs(pos.lat, pos.lng, true);
      });

      map.on('click', function (e) {
        draggableMarker.setLatLng(e.latlng);
        updateInputs(e.latlng.lat, e.latlng.lng, true);
      });

      // Relocate on user location click if requested
      if (options.enableGeolocation && navigator.geolocation) {
        map.locate({ setView: false, maxZoom: 16 });
        map.on('locationfound', function (e) {
          draggableMarker.setLatLng(e.latlng);
          updateInputs(e.latlng.lat, e.latlng.lng, true);
          map.setView(e.latlng, 15);
        });
      }

      // Current location GPS button helper
      if (options.locateBtnId) {
        const locateBtn = document.getElementById(options.locateBtnId);
        if (locateBtn && navigator.geolocation) {
          locateBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const originalHtml = locateBtn.innerHTML;
            locateBtn.disabled = true;
            locateBtn.innerHTML = `
              <span class="material-symbols-outlined spin-animation" style="font-size:16px;">sync</span>
              <span>Mencari lokasi GPS...</span>
            `;

            navigator.geolocation.getCurrentPosition(
              function (position) {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                draggableMarker.setLatLng([userLat, userLng]);
                map.setView([userLat, userLng], 16);
                updateInputs(userLat, userLng, true);
                locateBtn.disabled = false;
                locateBtn.innerHTML = originalHtml;
              },
              function (geoErr) {
                locateBtn.disabled = false;
                locateBtn.innerHTML = originalHtml;
                if (typeof showToast === 'function') {
                  showToast('Gagal mendeteksi lokasi GPS Anda. Pastikan izin lokasi diaktifkan.', 'warning');
                } else {
                  alert('Gagal mendeteksi lokasi GPS. Pastikan izin lokasi diaktifkan.');
                }
              },
              { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
          });
        }
      }
    }

    // Multiple markers mode (complaints list / dashboard)
    const markerGroup = L.featureGroup();
    if (options.markers && Array.isArray(options.markers) && options.markers.length > 0) {
      options.markers.forEach((item) => {
        if (!item.latitude || !item.longitude) return;

        const m = L.marker([parseFloat(item.latitude), parseFloat(item.longitude)]);
        let popupContent = `
          <div class="map-popup-card">
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
              <span class="ticket-code">${escapeHtml(item.ticket_code || '')}</span>
              <span class="badge-status ${escapeHtml(item.status_badge_class || 'badge-diterima')} badge-sm">
                ${escapeHtml(item.status || 'Diterima')}
              </span>
            </div>
            <div class="map-popup-title">
              <a href="${item.url || '#'}" class="text-teal text-decoration-none fw-semibold">
                ${escapeHtml(item.title || 'Pengaduan')}
              </a>
            </div>
            <div class="map-popup-address">
              <span class="material-symbols-outlined md-16 align-text-bottom">location_on</span>
              ${escapeHtml(item.address || '')}
            </div>
            ${item.photo ? `<img src="${item.photo}" alt="" style="width:100%; height:90px; object-fit:cover; border-radius:6px; margin-top:4px;">` : ''}
          </div>
        `;
        m.bindPopup(popupContent);
        markerGroup.addLayer(m);
      });

      markerGroup.addTo(map);

      // Fit map to bounds of all markers if more than 1
      if (options.markers.length > 1) {
        try {
          map.fitBounds(markerGroup.getBounds().pad(0.1));
        } catch (err) {
          // Fallback if bounds cannot be computed
        }
      }
    }

    // Force map to recalculate dimensions (prevents grey tile issues)
    setTimeout(() => {
      map.invalidateSize();
    }, 250);

    return { map, marker: draggableMarker, markerGroup };
  }

  /**
   * 6. Upvote Toggle System
   * Event delegation for .btn-upvote, fetch POST to /aduan-kota/api/upvote.php
   */
  function initUpvoteSystem() {
    document.addEventListener('click', function (e) {
      const upvoteBtn = e.target.closest('.btn-upvote');
      if (!upvoteBtn) return;

      e.preventDefault();

      const complaintId = upvoteBtn.getAttribute('data-complaint-id') || upvoteBtn.dataset.complaintId;
      if (!complaintId) return;

      // Prevent duplicate clicks while request is ongoing
      if (upvoteBtn.classList.contains('is-loading')) return;
      upvoteBtn.classList.add('is-loading');

      const csrfToken = getCsrfToken();
      const formData = new FormData();
      formData.append('complaint_id', complaintId);
      formData.append('csrf_token', csrfToken);

      fetch(appUrl('api/upvote.php'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
        .then((response) => {
          if (response.status === 401) {
            // User is not logged in
            showToast('Silakan masuk terlebih dahulu untuk memberikan dukungan.', 'warning');
            setTimeout(() => {
              window.location.href = appUrl('auth/login.php');
            }, 1200);
            throw new Error('Unauthorized');
          }
          if (!response.ok) {
            throw new Error(`Server returned status ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          if (data && data.success) {
            const countElem = upvoteBtn.querySelector('.upvote-count');
            const iconElem = upvoteBtn.querySelector('.material-symbols-outlined');

            if (data.upvoted) {
              upvoteBtn.classList.add('upvoted');
              if (iconElem) {
                iconElem.textContent = 'favorite';
                iconElem.style.fontVariationSettings = "'FILL' 1";
              }
              showToast(data.message || 'Dukungan berhasil ditambahkan.', 'success');
            } else {
              upvoteBtn.classList.remove('upvoted');
              if (iconElem) {
                iconElem.textContent = 'favorite_border';
                iconElem.style.fontVariationSettings = "'FILL' 0";
              }
              showToast(data.message || 'Dukungan dibatalkan.', 'info');
            }

            if (countElem && typeof data.total_upvotes !== 'undefined') {
              countElem.textContent = data.total_upvotes;
            } else if (countElem && typeof data.count !== 'undefined') {
              countElem.textContent = data.count;
            }
          } else {
            showToast(data.message || 'Gagal memperbarui dukungan.', 'error');
          }
        })
        .catch((err) => {
          if (err.message !== 'Unauthorized') {
            console.error('Upvote Error:', err);
            showToast('Gagal memproses dukungan. Silakan coba lagi.', 'error');
          }
        })
        .finally(() => {
          upvoteBtn.classList.remove('is-loading');
        });
    });
  }

  /**
   * 7. Comment System
   * Submit handler for comment form, fetch POST to /aduan-kota/api/comment.php
   */
  function initCommentSystem() {
    const commentForms = document.querySelectorAll('.comment-form, #comment-form');

    commentForms.forEach((form) => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const textarea = form.querySelector('textarea[name="body"], textarea[name="comment"]');
        const complaintIdInput = form.querySelector('input[name="complaint_id"]');

        if (!textarea) return;
        const commentBody = textarea.value.trim();

        if (commentBody.length === 0) {
          showToast('Komentar tidak boleh kosong.', 'warning');
          textarea.focus();
          return;
        }

        if (commentBody.length < 3) {
          showToast('Komentar terlalu pendek (minimal 3 karakter).', 'warning');
          textarea.focus();
          return;
        }

        const complaintId = complaintIdInput ? complaintIdInput.value : form.getAttribute('data-complaint-id');
        if (!complaintId) {
          showToast('ID Pengaduan tidak ditemukan.', 'error');
          return;
        }

        const csrfToken = getCsrfToken();
        const formData = new FormData();
        formData.append('complaint_id', complaintId);
        formData.append('body', commentBody);
        formData.append('csrf_token', csrfToken);

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.dataset.originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            Mengirim...
          `;
        }

        fetch(appUrl('api/comment.php'), {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
          .then((response) => {
            if (response.status === 401) {
              showToast('Silakan masuk terlebih dahulu untuk berkomentar.', 'warning');
              setTimeout(() => {
                window.location.href = appUrl('auth/login.php');
              }, 1200);
              throw new Error('Unauthorized');
            }
            if (!response.ok) {
              throw new Error(`Server returned status ${response.status}`);
            }
            return response.json();
          })
          .then((data) => {
            if (data && data.success) {
              textarea.value = '';

              // Reset character counter if present
              const counter = form.querySelector('.char-counter');
              if (counter && textarea.getAttribute('data-maxlength')) {
                const max = textarea.getAttribute('data-maxlength');
                counter.textContent = `0 / ${max} karakter`;
                counter.classList.remove('limit-reached');
              }

              // Prepend new comment into comment list
              const commentList = document.querySelector('#comment-list, .comment-list');
              if (commentList) {
                // If "no comments yet" placeholder is present, remove it
                const emptyPlaceholder = commentList.querySelector('.comment-empty-state');
                if (emptyPlaceholder) {
                  emptyPlaceholder.remove();
                }

                const newCommentHtml = renderCommentItem(data.comment || {
                  user_name: data.user_name || 'Anda',
                  user_photo: data.user_photo || null,
                  body: commentBody,
                  created_at: new Date().toISOString()
                });

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = newCommentHtml;
                const newElem = tempDiv.firstElementChild;
                newElem.classList.add('fade-in');
                commentList.prepend(newElem);
              }

              // Update comment count indicators on page
              const countBadges = document.querySelectorAll('.comment-count-badge, #comment-count');
              countBadges.forEach((badge) => {
                const currentCount = parseInt(badge.textContent.replace(/\D/g, ''), 10) || 0;
                badge.textContent = currentCount + 1;
              });

              showToast(data.message || 'Komentar berhasil dikirim.', 'success');
            } else {
              showToast(data.message || 'Gagal mengirim komentar.', 'error');
            }
          })
          .catch((err) => {
            if (err.message !== 'Unauthorized') {
              console.error('Comment Error:', err);
              showToast('Terjadi kesalahan saat mengirim komentar.', 'error');
            }
          })
          .finally(() => {
            if (submitBtn) {
              submitBtn.disabled = false;
              if (submitBtn.dataset.originalText) {
                submitBtn.innerHTML = submitBtn.dataset.originalText;
              }
            }
          });
      });
    });
  }

  /**
   * Helper to render HTML card for dynamic comment insertion
   */
  function renderCommentItem(comment) {
    const userName = escapeHtml(comment.user_name || 'Pengguna');
    const userInitial = userName.charAt(0).toUpperCase();
    const commentBody = escapeHtml(comment.body || '').replace(/\n/g, '<br>');
    const timeDisplay = comment.time_ago || timeAgo(comment.created_at || new Date());

    let avatarHtml;
    if (comment.user_photo) {
      avatarHtml = `<img src="${appUrl('uploads/' + escapeHtml(comment.user_photo))}" alt="${userName}" class="comment-avatar">`;
    } else {
      avatarHtml = `<div class="comment-avatar">${userInitial}</div>`;
    }

    return `
      <div class="comment-item">
        ${avatarHtml}
        <div class="comment-body">
          <div class="comment-header">
            <span class="comment-author-name">${userName}</span>
            <span class="comment-time">${timeDisplay}</span>
          </div>
          <p class="comment-text">${commentBody}</p>
        </div>
      </div>
    `;
  }

  /**
   * 8. Character Counter for textareas with [data-maxlength]
   */
  function initCharacterCounters() {
    const fields = document.querySelectorAll('textarea[data-maxlength], input[data-maxlength]');

    fields.forEach((field) => {
      const max = parseInt(field.getAttribute('data-maxlength'), 10);
      if (isNaN(max)) return;

      // Find or create sibling counter
      let counter = field.parentElement.querySelector('.char-counter');
      if (!counter) {
        counter = document.createElement('div');
        counter.className = 'char-counter';
        field.parentElement.appendChild(counter);
      }

      const updateCounter = () => {
        const currentLength = field.value.length;
        counter.textContent = `${currentLength} / ${max} karakter`;

        if (currentLength >= max) {
          counter.classList.add('limit-reached');
        } else {
          counter.classList.remove('limit-reached');
        }
      };

      field.addEventListener('input', updateCounter);
      updateCounter();
    });
  }

  /**
   * 9. Confirm Action / Delete Modals
   * Event delegation for elements with [data-confirm]
   */
  function initConfirmDialogs() {
    document.addEventListener('click', function (e) {
      const target = e.target.closest('[data-confirm]');
      if (!target) return;

      const confirmMessage = target.getAttribute('data-confirm') || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';

      if (!window.confirm(confirmMessage)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }

  /**
   * 10. Sidebar Toggle for Mobile (Admin Layout)
   */
  function initSidebarToggle() {
    const sidebar = document.querySelector('.sidebar');
    const togglers = document.querySelectorAll('#sidebarToggle, .sidebar-mobile-toggle, .sidebar-toggler');
    if (!sidebar) return;

    let backdrop = document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'sidebar-backdrop';
      document.body.appendChild(backdrop);
    }

    const openSidebar = () => {
      sidebar.classList.add('sidebar-open');
      backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
    };

    const closeSidebar = () => {
      sidebar.classList.remove('sidebar-open');
      backdrop.classList.remove('show');
      document.body.style.overflow = '';
    };

    togglers.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (sidebar.classList.contains('sidebar-open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    });

    backdrop.addEventListener('click', closeSidebar);

    // Auto-close when resizing back to desktop screen width
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992 && sidebar.classList.contains('sidebar-open')) {
        closeSidebar();
      }
    });
  }

  /**
   * 11. Smooth Scrolling for Internal Hash Anchors
   */
  function initSmoothScroll() {
    document.addEventListener('click', function (e) {
      const link = e.target.closest('a[href^="#"]:not([href="#"]):not([data-bs-toggle])');
      if (!link) return;

      const targetId = link.getAttribute('href');
      try {
        const targetElem = document.querySelector(targetId);
        if (targetElem) {
          e.preventDefault();
          const offsetTop = targetElem.getBoundingClientRect().top + window.pageYOffset - 80;
          window.scrollTo({
            top: offsetTop,
            behavior: 'smooth'
          });
        }
      } catch (err) {
        // Invalid selector, let standard behavior handle it
      }
    });
  }

  /**
   * 12. Form Validation Helpers
   */
  function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach((form) => {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });

    // Password confirmation matcher helper
    const passwordConfirmInputs = document.querySelectorAll('input[data-match-password]');
    passwordConfirmInputs.forEach((confirmInput) => {
      const targetId = confirmInput.getAttribute('data-match-password');
      const targetInput = document.getElementById(targetId);
      if (!targetInput) return;

      const validateMatch = () => {
        if (confirmInput.value && targetInput.value && confirmInput.value !== targetInput.value) {
          confirmInput.setCustomValidity('Konfirmasi kata sandi tidak cocok.');
        } else {
          confirmInput.setCustomValidity('');
        }
      };

      confirmInput.addEventListener('input', validateMatch);
      targetInput.addEventListener('input', validateMatch);
    });
  }

  // =========================================================================
  // DOMContentLoaded Bootstrapper
  // =========================================================================
  document.addEventListener('DOMContentLoaded', function () {
    initFlashMessages();
    initUpvoteSystem();
    initCommentSystem();
    initCharacterCounters();
    initConfirmDialogs();
    initSidebarToggle();
    initSmoothScroll();
    initFormValidation();

    // Auto-initialize standard file upload dropzone if present on page
    const standardFileInput = document.getElementById('complaint-photo-input');
    const standardPreviewBox = document.getElementById('complaint-photo-preview');
    if (standardFileInput && standardPreviewBox) {
      initFilePreview('complaint-photo-input', 'complaint-photo-preview');
    }
  });

  // Expose global utilities for page-specific initialization scripts
  window.LaporKota = {
    getCsrfToken,
    showToast,
    initFilePreview,
    initLeafletMap,
    timeAgo,
    escapeHtml,
    url: appUrl
  };

  // Top-level exports for direct calls
  window.initLeafletMap = initLeafletMap;
  window.initFilePreview = initFilePreview;
  window.showToast = showToast;
  window.getCsrfToken = getCsrfToken;

})();
