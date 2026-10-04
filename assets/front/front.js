document.addEventListener('DOMContentLoaded', initMdComments);

// Safe initialization wrapper to support dynamic, deferred, or instant execution
function initMdComments() {
    // Prevent duplicate execution
    if (window.__mdCommentsInitialized) return;
    window.__mdCommentsInitialized = true;

    // ─── Persian Digit Converter ───
    function toPersianNum(num) {
      const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
      return num.toString().replace(/\d/g, d => persianDigits[d]);
    }

    // ─── Star Rating ───
    const starsContainer = document.getElementById('starsContainer');
    const starBtns = starsContainer ? starsContainer.querySelectorAll('.star-btn') : [];
    const ratingValueEl = document.getElementById('ratingValue');
    const selectedRatingInput = document.getElementById('selectedRatingInput');
    let selectedRating = 0;

    function fillStars(upTo, isHover) {
      starBtns.forEach((btn, idx) => {
        const polygon = btn.querySelector('polygon');
        if (idx >= starBtns.length - upTo) {
          polygon.classList.remove('star-empty');
          polygon.classList.add('star-filled');
        } else if (!isHover || idx >= starBtns.length - selectedRating) {
          if (idx < starBtns.length - selectedRating) {
            polygon.classList.remove('star-filled');
            polygon.classList.add('star-empty');
          }
        } else {
          polygon.classList.remove('star-filled');
          polygon.classList.add('star-empty');
        }
      });
      
      // Update circle number dynamically
      const displayRating = isHover ? upTo : selectedRating;
      if (ratingValueEl) {
        ratingValueEl.textContent = displayRating > 0 ? toPersianNum(displayRating) : '۰';
      }
    }

    function clearStars() {
      starBtns.forEach(btn => {
        const polygon = btn.querySelector('polygon');
        polygon.classList.remove('star-filled');
        polygon.classList.add('star-empty');
      });
      fillStars(selectedRating, false);
    }

    starBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        selectedRating = parseInt(btn.dataset.value);
        if (selectedRatingInput) {
          selectedRatingInput.value = selectedRating;
        }
        fillStars(selectedRating, false);
      });
      btn.addEventListener('mouseenter', () => {
        fillStars(parseInt(btn.dataset.value), true);
      });
    });

    if (starsContainer) {
      starsContainer.addEventListener('mouseleave', clearStars);
    }

    // ─── Avatar Selection ───
    const avatarOptions = document.querySelectorAll('.avatar-option');
    const selectedAvatarInput = document.getElementById('selectedAvatarInput');
    avatarOptions.forEach(opt => {
      opt.addEventListener('click', () => {
        avatarOptions.forEach(o => {
          o.classList.remove('selected', 'border-gold', 'opacity-100', 'shadow-lg', 'shadow-gold/30', 'grayscale-0');
          o.classList.add('border-border', 'opacity-50');
          o.setAttribute('aria-checked', 'false');
        });
        opt.classList.remove('border-border', 'opacity-50');
        opt.classList.add('selected', 'border-gold', 'opacity-100', 'shadow-lg', 'shadow-gold/30', 'grayscale-0');
        opt.setAttribute('aria-checked', 'true');
        
        const avatarValue = opt.dataset.avatar;
        if (selectedAvatarInput) {
          selectedAvatarInput.value = avatarValue;
        }
      });
    });

    // Initialize first avatar if selected exists
    const initialSelectedAvatar = document.querySelector('.avatar-option.selected');
    if (initialSelectedAvatar) {
      initialSelectedAvatar.classList.add('border-gold', 'opacity-100', 'shadow-lg', 'shadow-gold/30', 'grayscale-0');
      initialSelectedAvatar.classList.remove('border-border', 'opacity-50');
    }

    // ─── Toast ───
    function showToast(msg) {
      const toast = document.getElementById('toast');
      if (toast) {
        toast.textContent = msg;
        toast.classList.add('toast-show');
        setTimeout(() => toast.classList.remove('toast-show'), 4000);
      }
    }

    // ─── Submit Comment ───
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
      submitBtn.addEventListener('click', () => {
        const name = document.getElementById('nameInput') ? document.getElementById('nameInput').value.trim() : '';
        const phone = document.getElementById('phoneInput') ? document.getElementById('phoneInput').value.trim() : '';
        const comment = document.getElementById('commentTextarea') ? document.getElementById('commentTextarea').value.trim() : '';
        const honeypotInput = document.getElementById('honeypotInput');
        const honeypotName = honeypotInput ? honeypotInput.name : 'md_hp_website';
        const honeypot = honeypotInput ? honeypotInput.value.trim() : '';
        
        // 1. Validate honeypot (Anti-spam)
        if (honeypot) {
          console.warn('Spam submission blocked via Honeypot.');
          showToast('دیدگاه شما با موفقیت ثبت شد ✓'); // Pretend success to bot
          resetForm();
          return;
        }

        // 2. Validate standard fields
        if (!name) return showToast('لطفاً نام و نام خانوادگی را وارد نمایید');
        
        // 3. Validate Phone Number (Iran Mobile Format: 09XXXXXXXXX)
        if (phone) {
          const iranPhoneRegex = /^09[0-9]{9}$/;
          if (!iranPhoneRegex.test(phone)) {
            return showToast('لطفاً شماره همراه معتبر (مثال: 09123456789) وارد نمایید');
          }
        }
        
        if (!comment) return showToast('لطفاً متن دیدگاه را وارد نمایید');
        if (selectedRating === 0) return showToast('لطفاً امتیاز خود را ثبت کنید');

        // Check if we are running in WordPress context and extract config from DOM
        const container = document.getElementById('mdCommentsContainer');
        const ajaxUrl = container ? container.dataset.ajaxUrl : (window.mdCommentsData ? window.mdCommentsData.ajaxUrl : '/wp-admin/admin-ajax.php');
        const nonce = container ? container.dataset.nonce : (window.mdCommentsData ? window.mdCommentsData.nonce : '');
        const postId = container ? container.dataset.postId : (window.mdCommentsData ? window.mdCommentsData.postId : 0);

        const isWordPress = !!(container || (window.mdCommentsData && window.mdCommentsData.ajaxUrl) || typeof wp !== 'undefined');

        if (isWordPress) {
          // Submit via AJAX/REST API (WordPress)
          const formData = new FormData();
          formData.append('action', 'md_submit_comment');
          formData.append('name', name);
          formData.append('phone', phone);
          formData.append('comment', comment);
          formData.append('rating', selectedRating);
          formData.append('avatar', selectedAvatarInput ? selectedAvatarInput.value : '1');
          formData.append(honeypotName, honeypot);
          formData.append('post_id', postId);
          
          // Add WP Nonce if available
          if (nonce) {
            formData.append('_ajax_nonce', nonce);
          }

          submitBtn.disabled = true;
          submitBtn.classList.add('opacity-50', 'cursor-not-allowed');

          fetch(ajaxUrl, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(res => {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            
            if (res.success) {
              const data = res.data;
              showToast(data.message || 'دیدگاه شما با موفقیت ثبت شد ✓');
              
              // If the comment is approved instantly, append it to comments list
              if (data.is_approved && data.html) {
                const commentsList = document.getElementById('commentsList');
                if (commentsList) {
                  // Remove "no comments" message if it exists
                  const noCommentsText = document.getElementById('noCommentsText');
                  if (noCommentsText) {
                    noCommentsText.remove();
                  }

                  // Create element from HTML string and append
                  const parser = new DOMParser();
                  const doc = parser.parseFromString(data.html, 'text/html');
                  // Use firstElementChild (not firstChild) to skip whitespace text nodes
                  // that precede the actual <div> in the server-rendered HTML.
                  const newCommentNode = doc.body.querySelector('.user-comment') || doc.body.firstElementChild;

                  if (newCommentNode) {
                    commentsList.appendChild(newCommentNode);
                    newCommentNode.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Bind reaction listeners to new comment
                    initReactions();
                  }
                }
              }
              resetForm();
            } else {
              showToast(res.data || 'خطایی در ارسال دیدگاه رخ داد');
            }
          })
          .catch(err => {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            showToast('خطا در اتصال به سرور');
            console.error(err);
          });

        } else {
          // Static Mode - Append to DOM directly (For preview/mock testing)
          const selectedAvatar = document.querySelector('.avatar-option.selected svg');
          if (selectedAvatar) {
            const avatarClone = selectedAvatar.cloneNode(true);
            avatarClone.setAttribute('class', 'w-14 h-14');

            const commentItem = document.createElement('div');
            commentItem.className = 'flex gap-4 items-start user-comment';
            
            commentItem.innerHTML = `
              <div class="w-24 h-24 rounded-full border-2 border-border flex-shrink-0 overflow-hidden bg-gray-50 flex items-center justify-center"></div>
              <div class="flex-1 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                  <span class="font-bold text-lg text-primary">${name}</span>
                  <span class="text-sm text-neutral">لحظاتی پیش</span>
                </div>
                <div class="text-lg leading-9 text-gray-800">
                  ${comment}
                </div>
              </div>
            `;
            
            commentItem.querySelector('.w-24').appendChild(avatarClone);

            const commentsList = document.getElementById('commentsList');
            if (commentsList) {
              const noCommentsText = document.getElementById('noCommentsText');
              if (noCommentsText) {
                noCommentsText.remove();
              }
              
              commentsList.appendChild(commentItem);
              commentItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }

          resetForm();
          showToast('دیدگاه شما با موفقیت ثبت شد ✓ (حالت نمایشی)');
        }
      });
    }

    function resetForm() {
      if (document.getElementById('nameInput')) document.getElementById('nameInput').value = '';
      if (document.getElementById('phoneInput')) document.getElementById('phoneInput').value = '';
      if (document.getElementById('commentTextarea')) document.getElementById('commentTextarea').value = '';
      selectedRating = 0;
      if (selectedRatingInput) selectedRatingInput.value = '0';
      clearStars();
    }

    // ─── Load More Comments (Lazy Load) ───
    const loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
    if (loadMoreBtn) {
      loadMoreBtn.addEventListener('click', () => {
        const container = document.getElementById('mdCommentsContainer');
        const ajaxUrl = container ? container.dataset.ajaxUrl : (window.mdCommentsData ? window.mdCommentsData.ajaxUrl : '/wp-admin/admin-ajax.php');
        const nonce = container ? container.dataset.nonce : (window.mdCommentsData ? window.mdCommentsData.nonce : '');
        const postId = container ? container.dataset.postId : (window.mdCommentsData ? window.mdCommentsData.postId : 0);
        const nextPage = loadMoreBtn.dataset.nextPage || '2';

        loadMoreBtn.disabled = true;
        loadMoreBtn.classList.add('opacity-50', 'cursor-not-allowed');

        const formData = new FormData();
        formData.append('action', 'md_load_more_comments');
        formData.append('post_id', postId);
        formData.append('page', nextPage);
        if (nonce) {
          formData.append('_ajax_nonce', nonce);
        }

        fetch(ajaxUrl, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(res => {
          loadMoreBtn.disabled = false;
          loadMoreBtn.classList.remove('opacity-50', 'cursor-not-allowed');

          if (res.success) {
            const commentsList = document.getElementById('commentsList');
            if (commentsList && res.data.html) {
              const parser = new DOMParser();
              const doc = parser.parseFromString(res.data.html, 'text/html');
              // .children فقط المنت‌ها را برمی‌گرداند (نه Text Node های فاصله خالی بین آن‌ها)
              Array.from(doc.body.children).forEach(node => commentsList.appendChild(node));
              initReactions();
            }

            if (res.data.has_more) {
              loadMoreBtn.dataset.nextPage = res.data.next_page;
            } else {
              loadMoreBtn.remove();
            }
          } else {
            showToast(res.data || 'خطا در بارگذاری دیدگاه‌های بیشتر');
          }
        })
        .catch(err => {
          loadMoreBtn.disabled = false;
          loadMoreBtn.classList.remove('opacity-50', 'cursor-not-allowed');
          showToast('خطا در ارتباط با سرور');
          console.error(err);
        });
      });
    }

    // ─── Comment Reactions ───
    function initReactions() {
      const reactBtns = document.querySelectorAll('.btn-react');
      
      reactBtns.forEach(btn => {
        if (btn.dataset.listenerBound) return;
        btn.dataset.listenerBound = 'true';
        
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const commentId = btn.dataset.commentId;
          const reactionType = btn.dataset.type;
          
          if (!commentId || !reactionType) return;
          
          const container = document.getElementById('mdCommentsContainer');
          const isWordPress = !!(container || (window.mdCommentsData && window.mdCommentsData.ajaxUrl) || typeof wp !== 'undefined');
          
          if (isWordPress) {
            const userLoggedIn = container ? container.dataset.userLoggedIn === 'true' : false;
            if (!userLoggedIn) {
              showToast('لطفا ابتدا وارد شوید');
              return;
            }
          }
          
          const ajaxUrl = container ? container.dataset.ajaxUrl : (window.mdCommentsData ? window.mdCommentsData.ajaxUrl : '/wp-admin/admin-ajax.php');
          const nonce = container ? container.dataset.nonce : (window.mdCommentsData ? window.mdCommentsData.nonce : '');
          
          if (isWordPress) {
            // WordPress Mode: AJAX call
            const formData = new FormData();
            formData.append('action', 'md_react_comment');
            formData.append('comment_id', commentId);
            formData.append('type', reactionType);
            if (nonce) {
              formData.append('_ajax_nonce', nonce);
            }
            
            // Disable reaction buttons for this comment during request to avoid spamming
            const commentReactBtns = document.querySelectorAll(`.btn-react[data-comment-id="${commentId}"]`);
            commentReactBtns.forEach(b => b.style.pointerEvents = 'none');
            
            fetch(ajaxUrl, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(res => {
              commentReactBtns.forEach(b => b.style.pointerEvents = 'auto');
              if (res.success) {
                const data = res.data; // { likes, dislikes, user_reaction }
                
                // Update reaction counts in DOM
                const likeCountEl = document.querySelector(`.btn-like[data-comment-id="${commentId}"] .like-count`);
                const dislikeCountEl = document.querySelector(`.btn-dislike[data-comment-id="${commentId}"] .dislike-count`);
                
                if (likeCountEl) likeCountEl.textContent = toPersianNum(data.likes);
                if (dislikeCountEl) dislikeCountEl.textContent = toPersianNum(data.dislikes);
                
                // Update LocalStorage reaction state
                let reactions = JSON.parse(localStorage.getItem('md_reactions') || '{}');
                if (data.user_reaction) {
                  reactions[commentId] = data.user_reaction;
                } else {
                  delete reactions[commentId];
                }
                localStorage.setItem('md_reactions', JSON.stringify(reactions));
                
                // Update active state visual classes
                updateReactionClasses(commentId, data.user_reaction);
              } else {
                showToast(res.data || 'خطایی در ثبت واکنش رخ داد');
              }
            })
            .catch(err => {
              commentReactBtns.forEach(b => b.style.pointerEvents = 'auto');
              console.error(err);
              showToast('خطا در ارتباط با سرور');
            });
            
          } else {
            // Static / Preview Mode: Simulate reactions in local storage
            let reactions = JSON.parse(localStorage.getItem('md_reactions') || '{}');
            const oldReaction = reactions[commentId];
            let newReaction = null;
            
            const likeEl = document.querySelector(`.btn-like[data-comment-id="${commentId}"]`);
            const dislikeEl = document.querySelector(`.btn-dislike[data-comment-id="${commentId}"]`);
            const likeCountEl = likeEl ? likeEl.querySelector('.like-count') : null;
            const dislikeCountEl = dislikeEl ? dislikeEl.querySelector('.dislike-count') : null;
            
            const p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
            let likes = likeCountEl ? parseInt(p2e(likeCountEl.textContent.trim())) || 0 : 0;
            let dislikes = dislikeCountEl ? parseInt(p2e(dislikeCountEl.textContent.trim())) || 0 : 0;
            
            if (oldReaction === reactionType) {
              // Retract
              if (reactionType === 'like') likes = Math.max(0, likes - 1);
              if (reactionType === 'dislike') dislikes = Math.max(0, dislikes - 1);
              delete reactions[commentId];
            } else {
              // Switch or new reaction
              if (oldReaction === 'like') likes = Math.max(0, likes - 1);
              if (oldReaction === 'dislike') dislikes = Math.max(0, dislikes - 1);
              
              if (reactionType === 'like') likes++;
              if (reactionType === 'dislike') dislikes++;
              
              reactions[commentId] = reactionType;
              newReaction = reactionType;
            }
            
            localStorage.setItem('md_reactions', JSON.stringify(reactions));
            if (likeCountEl) likeCountEl.textContent = toPersianNum(likes);
            if (dislikeCountEl) dislikeCountEl.textContent = toPersianNum(dislikes);
            
            updateReactionClasses(commentId, newReaction);
          }
        });
      });
    }

    function updateReactionClasses(commentId, userReaction) {
      const likeEl = document.querySelector(`.btn-like[data-comment-id="${commentId}"]`);
      const dislikeEl = document.querySelector(`.btn-dislike[data-comment-id="${commentId}"]`);
      
      if (likeEl) {
        if (userReaction === 'like') {
          likeEl.classList.add('active');
        } else {
          likeEl.classList.remove('active');
        }
      }
      
      if (dislikeEl) {
        if (userReaction === 'dislike') {
          dislikeEl.classList.add('active');
        } else {
          dislikeEl.classList.remove('active');
        }
      }
    }

    function syncAllReactionsFromStorage() {
      const reactions = JSON.parse(localStorage.getItem('md_reactions') || '{}');
      Object.keys(reactions).forEach(commentId => {
        updateReactionClasses(commentId, reactions[commentId]);
      });
    }

    // Initialize reactions and sync visual state
    initReactions();
    syncAllReactionsFromStorage();
}

// Fallback execution if DOMContentLoaded already fired
if (document.readyState !== 'loading') {
    initMdComments();
}
