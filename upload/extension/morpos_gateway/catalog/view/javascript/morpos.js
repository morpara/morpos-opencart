/**
 * MorPOS Payment Gateway - OpenCart
 * Handles payment modal, iframe communication, and checkout flow
 * Compatible with OpenCart 3 and OpenCart 4
 */
(function(){
  'use strict';

  var MorposCheckout = {
    modal: null,
    modalContent: null,
    closeBtn: null,
    iframe: null,
    loadingDiv: null,
    confirmButton: null,
    currentBlobUrl: null,
    isProcessing: false,

    init: function() {
      this.modal = document.getElementById('morpos-modal');
      this.modalContent = document.getElementById('morpos-modal-content');
      this.closeBtn = document.getElementById('morpos-close-btn');
      this.iframe = document.getElementById('morpos-iframe');
      this.loadingDiv = document.getElementById('morpos-loading');
      this.confirmButton = document.getElementById('button-confirm');

      if (!this.modal || !this.confirmButton) return;

      this.setupEventListeners();
    },

    setupEventListeners: function() {
      var self = this;

      // Close button click
      if (this.closeBtn) {
        this.closeBtn.addEventListener('click', function() {
          self.closeModal();
        });
      }

      // Iframe load event - hide loading when iframe content is ready
      if (this.iframe) {
        this.iframe.addEventListener('load', function() {
          self.hideLoading();
        });
      }

      // ESC key to close modal
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && self.modal && self.modal.classList.contains('modal-show')) {
          self.closeModal();
        }
      });

      // PostMessage listener for iframe communication
      window.addEventListener('message', this.handleIframeMessage.bind(this));

      // Confirm button click
      this.confirmButton.addEventListener('click', this.handleConfirmClick.bind(this));
    },

    handleConfirmClick: function() {
      if (this.isProcessing) return;

      this.isProcessing = true;
      this.disableConfirmButton();

      var self = this;
      
      // Use fetch API if available, otherwise fallback to XMLHttpRequest
      if (typeof fetch !== 'undefined') {
        fetch(window.morposConfig.confirmUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
          .then(function(r) { return r.json(); })
          .then(function(res) {
            self.handleResponse(res);
          })
          .catch(function(error) {
            self.handleError(error);
          });
      } else {
        // Fallback for older browsers
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.morposConfig.confirmUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function() {
          if (xhr.status === 200) {
            try {
              var res = JSON.parse(xhr.responseText);
              self.handleResponse(res);
            } catch (e) {
              self.handleError(e);
            }
          } else {
            self.handleError(new Error('HTTP ' + xhr.status));
          }
        };
        xhr.onerror = function() {
          self.handleError(new Error('Network error'));
        };
        xhr.send();
      }
    },

    handleResponse: function(res) {
      if (res.redirect) {
        window.location = res.redirect;
      } else if (res.html) {
        this.showIframe(res.html);
      } else {
        var errorMsg = res.error || window.morposConfig.textPaymentInitFailed;
        this.showError(errorMsg);
        this.enableConfirmButton();
        this.isProcessing = false;
      }
    },

    handleError: function(error) {
      console.error('Payment request failed:', error);
      this.showError(window.morposConfig.textNetworkError);
      this.enableConfirmButton();
      this.isProcessing = false;
    },

    openModal: function() {
      if (!this.modal) return;
      this.modal.classList.add('modal-show');
      document.body.classList.add('morpos-modal-open');
    },

    closeModal: function() {
      if (!this.modal) return;
      this.modal.classList.remove('modal-show');
      document.body.classList.remove('morpos-modal-open');

      if (this.iframe) {
        this.iframe.src = 'about:blank';
      }

      this.hideLoading();
      this.enableConfirmButton();
      this.isProcessing = false;

      if (this.currentBlobUrl) {
        URL.revokeObjectURL(this.currentBlobUrl);
        this.currentBlobUrl = null;
      }
    },

    showLoading: function() {
      this.openModal();
      if (this.loadingDiv) {
        this.loadingDiv.style.display = 'block';
      }
      if (this.iframe) {
        this.iframe.style.display = 'none';
      }
    },

    hideLoading: function() {
      if (this.loadingDiv) {
        this.loadingDiv.style.display = 'none';
      }
      if (this.iframe) {
        this.iframe.style.display = 'block';
      }
    },

    showIframe: function(html) {
      if (!this.iframe) return;

      if (this.currentBlobUrl) {
        URL.revokeObjectURL(this.currentBlobUrl);
        this.currentBlobUrl = null;
      }

      var blob = new Blob([html], { type: 'text/html; charset=utf-8' });
      this.currentBlobUrl = URL.createObjectURL(blob);

      this.iframe.src = this.currentBlobUrl;
      
      // Show modal with loading state first
      this.showLoading();
    },

    showError: function(message) {
      this.closeModal();
      
      // Escape HTML to prevent XSS
      var escapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      };
      
      var errorContainer = document.querySelector('.alert-danger, .text-danger');
      if (!errorContainer) {
        errorContainer = document.createElement('div');
        errorContainer.className = 'alert alert-danger';
        errorContainer.style.cssText = 'margin: 10px 0; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; background-color: #f8d7da;';
        
        var buttonContainer = this.confirmButton.parentNode;
        buttonContainer.parentNode.insertBefore(errorContainer, buttonContainer);
      }
      
      errorContainer.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(message);
      errorContainer.style.display = 'block';
      
      setTimeout(function() {
        if (errorContainer.scrollIntoView) {
          errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 100);
    },

    handleIframeMessage: function(event) {
      if (!event.data || typeof event.data !== 'object') return;

      if (event.data.type === 'MORPOS_RESULT') {
        var status = event.data.status;
        var message = event.data.message || '';

        if (status === 'success') {
          this.closeModal();
          var successUrl = event.data.redirect_url || window.morposConfig.redirectSuccess;
          window.location.href = successUrl;
        } else if (status === 'failure' || status === 'error') {
          // Use the message from postMessage if available, otherwise use default
          var errorMsg = message || window.morposConfig.textPaymentFailedDefault;
          this.showError(errorMsg);
          this.enableConfirmButton();
        }
      }
    },

    disableConfirmButton: function() {
      if (this.confirmButton) {
        this.confirmButton.disabled = true;
        
        // Store original text
        var isInput = this.confirmButton.tagName.toLowerCase() === 'input';
        if (!this.confirmButton.dataset) {
          this.confirmButton.dataset = {};
        }
        if (!this.confirmButton.dataset.originalText) {
          this.confirmButton.dataset.originalText = isInput ? this.confirmButton.value : this.confirmButton.textContent;
        }
        
        // Set loading text
        var loadingText = window.morposConfig.textRedirecting || window.morposConfig.textLoading || 'Loading...';
        if (isInput) {
          this.confirmButton.value = loadingText;
        } else {
          this.confirmButton.textContent = loadingText;
        }
      }
    },

    enableConfirmButton: function() {
      if (this.confirmButton) {
        this.confirmButton.disabled = false;
        
        // Restore original text
        if (this.confirmButton.dataset && this.confirmButton.dataset.originalText) {
          var isInput = this.confirmButton.tagName.toLowerCase() === 'input';
          if (isInput) {
            this.confirmButton.value = this.confirmButton.dataset.originalText;
          } else {
            this.confirmButton.textContent = this.confirmButton.dataset.originalText;
          }
        }
      }
    }
  };

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      MorposCheckout.init();
    });
  } else {
    MorposCheckout.init();
  }
})();
