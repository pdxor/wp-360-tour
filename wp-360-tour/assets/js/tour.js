/* /assets/js/tour.js */
(function() {
    'use strict';

    const DEBUG_MODE = false;
    let activeHotspot = null;
    let enableHotspotCreation = false;
    window.autoRotateEnabled = false;
    let isUserInteracting = false;
    let longitude = 0;
    let latitude = 0;
    let onPointerDownPointerX = 0;
    let onPointerDownPointerY = 0;
    let onPointerDownLongitude = 0;
    let onPointerDownLatitude = 0;
    const SPHERE_RADIUS = 500;
    const CAMERA_DISTANCE = 1;
    const hotspots = [];
    const DEBUG = true;

    function log(...args) {
        if (DEBUG) console.log(...args);
    }

    // Scene setup
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(90, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer();
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.getElementById('panoramaContainer').appendChild(renderer.domElement);

    // Create sphere geometry
    const geometry = new THREE.SphereGeometry(SPHERE_RADIUS, 60, 40);
    geometry.scale(-1, 1, 1);

    // Create texture from the panorama
    const panoramaUrl = tourData.panoramaUrl;
    const texture = new THREE.TextureLoader().load(panoramaUrl);
    const material = new THREE.MeshBasicMaterial({ map: texture });
    const sphere = new THREE.Mesh(geometry, material);
    scene.add(sphere);

    // Set initial camera position
    camera.position.set(0, 0, CAMERA_DISTANCE);

    // Raycaster setup
    const raycaster = new THREE.Raycaster();
    const mouse = new THREE.Vector2();

    // Event handlers
    function onWindowResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function onPointerDown(event) {
        event.preventDefault();
        isUserInteracting = true;

        const pointer = event.touches ? event.touches[0] : event;
        onPointerDownPointerX = pointer.clientX;
        onPointerDownPointerY = pointer.clientY;
        onPointerDownLongitude = longitude;
        onPointerDownLatitude = latitude;
    }

    function onPointerMove(event) {
        if (!isUserInteracting) return;

        const pointer = event.touches ? event.touches[0] : event;
        longitude = (onPointerDownPointerX - pointer.clientX) * 0.2 + onPointerDownLongitude;
        latitude = (onPointerDownPointerY - pointer.clientY) * 0.2 + onPointerDownLatitude;
        latitude = Math.max(-85, Math.min(85, latitude));

        requestAnimationFrame(updateHotspotPositions);
    }

    function onPointerUp() {
        isUserInteracting = false;
    }

    function onDocumentWheel(event) {
        const fov = camera.fov + event.deltaY * 0.05;
        camera.fov = Math.max(30, Math.min(90, fov));
        camera.updateProjectionMatrix();
    }

    // Hotspot management
    function createHotspot(data) {
        log('Creating hotspot:', data);
        
        const hotspot = {
            id: data.id || null,
            position: data.position.clone(),
            label: data.label,
            element: document.createElement('div')
        };

        hotspot.element.className = 'hotspot';
        if (data.id) {
            hotspot.element.setAttribute('data-hotspot-id', data.id);
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = data.label;
        hotspot.element.appendChild(tooltip);
        document.body.appendChild(hotspot.element);

        hotspot.element.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (enableHotspotCreation) {
                return; // Don't open modal when in creation mode
            }
            if (data.id) {
                openHotspotModal(data.id);
            }
        });

        hotspots.push(hotspot);
        updateHotspotPosition(hotspot);
        return hotspot;
    }

    function updateHotspotPosition(hotspot) {
        if (!hotspot.element || !hotspot.position) return;

        const cameraDirection = new THREE.Vector3(0, 0, -1);
        cameraDirection.applyQuaternion(camera.quaternion);

        const toHotspot = hotspot.position.clone().sub(camera.position).normalize();
        const dotProduct = cameraDirection.dot(toHotspot);

        const screenPosition = hotspot.position.clone();
        screenPosition.project(camera);

        const x = Math.round((screenPosition.x + 1) * window.innerWidth / 2);
        const y = Math.round((-screenPosition.y + 1) * window.innerHeight / 2);

        if (dotProduct > 0) {
            hotspot.element.style.display = 'block';
            hotspot.element.style.left = `${x}px`;
            hotspot.element.style.top = `${y}px`;
            const scale = Math.pow(dotProduct, 0.5);
            hotspot.element.style.transform = `translate(-50%, -50%) scale(${scale})`;
            hotspot.element.style.opacity = scale.toString();
        } else {
            hotspot.element.style.display = 'none';
        }
    }

    function updateHotspotPositions() {
        hotspots.forEach(updateHotspotPosition);
    }

    // Modal handling
    window.openHotspotModal = function(hotspotId) {
        const data = new FormData();
        data.append('action', 'get_hotspot_data');
        data.append('hotspot_id', hotspotId);
        data.append('nonce', wpAjax.nonce);

        fetch(wpAjax.ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = document.getElementById('hotspotModal');
                const title = document.getElementById('hotspotTitle');
                const image = document.getElementById('hotspotImage');
                const text = document.getElementById('hotspotText');
                const error = document.getElementById('modalError');
                
                error.style.display = 'none';
                title.textContent = data.data.title;
                text.innerHTML = data.data.content;
                
                if (data.data.featured_image) {
                    image.src = data.data.featured_image;
                    image.style.display = 'block';
                } else {
                    image.style.display = 'none';
                }

                modal.style.display = 'block';
            } else {
                const error = document.getElementById('modalError');
                error.textContent = data.data?.message || 'Error loading hotspot data';
                error.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('modalError');
            errorDiv.textContent = 'Error loading hotspot data';
            errorDiv.style.display = 'block';
        });
    }

    window.closeHotspotModal = function() {
        document.getElementById('hotspotModal').style.display = 'none';
    }

    // Form handling
    function initForm() {
        const form = document.getElementById('saveHotspotForm');
        const titleInput = form.querySelector('input[name="title"]');
        const contentInput = form.querySelector('textarea[name="content"]');
        const loadingOverlay = document.querySelector('.loading-overlay');

        if (!form || !titleInput || !contentInput) {
            console.error('Form elements not found:', {
                form: form,
                titleInput: titleInput,
                contentInput: contentInput
            });
            return;
        }

        // Prevent panorama interaction when form is active
        form.addEventListener('mousedown', function(e) {
            e.stopPropagation();
        }, true);
        
        form.addEventListener('touchstart', function(e) {
            e.stopPropagation();
        }, { passive: false });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get form inputs within the submit handler
            const titleInput = this.querySelector('input[name="title"]');
            const contentInput = this.querySelector('textarea[name="content"]');
            
            if (!titleInput || !contentInput) {
                console.error('Form inputs not found during submission');
                return;
            }
            
            const title = titleInput.value.trim();
            const content = contentInput.value.trim();
            
            if (!title) {
                alert('Please enter a title for the hotspot');
                titleInput.focus();
                return;
            }

            // Show loading animation
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.style.display = 'flex';
            
            const loadingImage = document.createElement('img');
            loadingImage.src = '/wp-content/uploads/2024/12/animate.gif';
            loadingImage.className = 'custom-loader';
            loadingImage.alt = 'Loading...';
            
            loadingOverlay.appendChild(loadingImage);
            document.body.appendChild(loadingOverlay);

            try {
                const formData = new FormData(this);
                formData.append('_ajax_nonce', wpAjax.nonce);
                
                // Log form data for debugging
                console.log('Submitting form data:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                const response = await fetch(wpAjax.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();
                console.log('Server response:', data);

                if (data.success) {
                    // Create permanent hotspot
                    if (activeHotspot) {
                        activeHotspot.id = data.data.hotspot_id;
                        activeHotspot.label = title;
                        const tooltip = activeHotspot.element.querySelector('.tooltip');
                        if (tooltip) {
                            tooltip.textContent = title;
                        }
                        activeHotspot.element.setAttribute('data-hotspot-id', data.data.hotspot_id);
                    }
                    
                    // Hide form
                    document.getElementById('hotspotForm').style.display = 'none';
                    
                    // Clear form
                    titleInput.value = '';
                    contentInput.value = '';
                    
                    // Show success message with smooth reload
                    const successOverlay = document.createElement('div');
                    successOverlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.7);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 100001;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    `;
                    
                    const successMessage = document.createElement('div');
                    successMessage.style.cssText = `
                        background: #28a745;
                        color: white;
                        padding: 20px 40px;
                        border-radius: 10px;
                        font-size: 18px;
                        transform: translateY(20px);
                        transition: transform 0.3s ease;
                    `;
                    successMessage.textContent = 'Hotspot saved successfully!';
                    
                    successOverlay.appendChild(successMessage);
                    document.body.appendChild(successOverlay);
                    
                    // Trigger animations
                    requestAnimationFrame(() => {
                        successOverlay.style.opacity = '1';
                        successMessage.style.transform = 'translateY(0)';
                    });
                    
                    // Reload after animation
                    setTimeout(() => {
                        successOverlay.style.opacity = '0';
                        successMessage.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            location.reload();
                        }, 300);
                    }, 1000);
                } else {
                    console.error('Server error:', data);
                    alert(data.data ? data.data.message : 'Error saving hotspot');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving hotspot');
            } finally {
                // Remove loading overlay
                document.body.removeChild(loadingOverlay);
            }
        });
    }

    // Animation loop
    function animate() {
        requestAnimationFrame(animate);

        if (!isUserInteracting && window.autoRotateEnabled) {
            longitude += 0.2;
            if (longitude >= 360) {
                longitude = 0;
            }
        }

        latitude = Math.max(-85, Math.min(85, latitude));
        const phi = THREE.MathUtils.degToRad(90 - latitude);
        const theta = THREE.MathUtils.degToRad(longitude);

        camera.position.x = CAMERA_DISTANCE * Math.sin(phi) * Math.cos(theta);
        camera.position.y = CAMERA_DISTANCE * Math.cos(phi);
        camera.position.z = CAMERA_DISTANCE * Math.sin(phi) * Math.sin(theta);

        camera.lookAt(scene.position);
        camera.updateMatrixWorld(true);

        updateHotspotPositions();
        renderer.render(scene, camera);
    }

    function showHotspotForm() {
        const form = document.getElementById('hotspotForm');
        form.style.display = 'block';
        form.classList.add('active');
        document.getElementById('hotspotTitle').focus();
    }

    function hideHotspotForm() {
        const form = document.getElementById('hotspotForm');
        form.style.display = 'none';
        form.classList.remove('active');
        if (activeHotspot) {
            activeHotspot.element.remove();
            const index = hotspots.indexOf(activeHotspot);
            if (index > -1) {
                hotspots.splice(index, 1);
            }
            activeHotspot = null;
        }
    }

    // Wrap everything in an IIFE and expose init globally
    (function() {
        'use strict';

        // Move these variables to be accessible in the entire scope
        let enableHotspotCreation = false;
        let autoRotateEnabled = false;

        window.init = function() {
            // Event listeners
            window.addEventListener('resize', onWindowResize);
            document.addEventListener('mousedown', onPointerDown);
            document.addEventListener('mousemove', onPointerMove);
            document.addEventListener('mouseup', onPointerUp);
            document.addEventListener('wheel', onDocumentWheel);
            document.addEventListener('touchstart', onPointerDown);
            document.addEventListener('touchmove', onPointerMove);
            document.addEventListener('touchend', onPointerUp);

            // Controls
            const hotspotCreationCheckbox = document.getElementById('enableHotspotCreation');
            const autoRotateButton = document.getElementById('autoRotateButton');

            if (hotspotCreationCheckbox) {
                hotspotCreationCheckbox.addEventListener('change', function() {
                    enableHotspotCreation = this.checked;
                    console.log('Hotspot creation enabled:', enableHotspotCreation);
                });
            }

            if (autoRotateButton) {
                autoRotateButton.addEventListener('click', function() {
                    window.autoRotateEnabled = !window.autoRotateEnabled;
                    this.classList.toggle('active', window.autoRotateEnabled);
                    console.log('Auto rotate enabled:', window.autoRotateEnabled);
                });
            }

            // Load existing hotspots
            function loadExistingHotspots() {
                if (typeof tourData !== 'undefined' && tourData.hotspots) {
                    console.log('Loading existing hotspots:', tourData.hotspots);
                    tourData.hotspots.forEach(hotspotData => {
                        createHotspot({
                            id: hotspotData.id,
                            position: new THREE.Vector3(
                                hotspotData.position.x,
                                hotspotData.position.y,
                                hotspotData.position.z
                            ),
                            label: hotspotData.title
                        });
                    });
                }
            }

            // Initialize form
            initForm();

            // Load existing hotspots
            loadExistingHotspots();

            // Start animation
            animate();

            // Click handler for hotspot creation
            renderer.domElement.addEventListener('click', function(event) {
                console.log('Click event, enableHotspotCreation:', enableHotspotCreation);
                if (!enableHotspotCreation) return;
                
                // Get click coordinates
                mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
                mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
                
                raycaster.setFromCamera(mouse, camera);
                const intersects = raycaster.intersectObject(sphere);
                
                if (intersects.length > 0) {
                    const point = intersects[0].point;
                    // Create temporary hotspot
                    if (activeHotspot) {
                        activeHotspot.element.remove();
                        const index = hotspots.indexOf(activeHotspot);
                        if (index > -1) {
                            hotspots.splice(index, 1);
                        }
                    }
                    activeHotspot = createHotspot({
                        position: point,
                        label: 'New Hotspot'
                    });
                    
                    document.getElementById('positionX').value = point.x;
                    document.getElementById('positionY').value = point.y;
                    document.getElementById('positionZ').value = point.z;
                    document.getElementById('hotspotForm').style.display = 'block';
                    document.getElementById('hotspotTitle').focus();
                }
            });

            // Add cancel button handler
            document.querySelector('.cancel-button').addEventListener('click', function() {
                document.getElementById('hotspotForm').style.display = 'none';
            });
        }
    })();
})();