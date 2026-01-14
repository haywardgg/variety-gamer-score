const init = async () => {
    const selectedCount = document.getElementById('selectedCount');
    const notPlayedCount = document.getElementById('notPlayedCount');
    const scorePercent = document.getElementById('scorePercent');
    const gameProgress = document.getElementById('gameProgress');
    const gameProgressBar = document.getElementById('gameProgressBar');
    const gameName = document.getElementById('gameName');
    const gameStatus = document.getElementById('gameStatus');
    const shareText = document.getElementById('shareText');
    const summaryLists = document.getElementById('summaryLists');
    const playedList = document.getElementById('playedList');
    const notPlayedList = document.getElementById('notPlayedList');
    const completionCountEl = document.getElementById('completionCount');
    const prevBtn = document.getElementById('prevBtn');
    const playedBtn = document.getElementById('playedBtn');
    const notPlayedBtn = document.getElementById('notPlayedBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const shareBtn = document.getElementById('shareBtn');
    const resetBtn = document.getElementById('resetBtn');
    const controlsButtons = document.getElementById('controlsButtons');
    const gameThumbnailPicture = document.getElementById('gameThumbnailPicture');
    const requiredElements = [
        selectedCount,
        notPlayedCount,
        scorePercent,
        gameProgress,
        gameProgressBar,
        gameName,
        gameStatus,
        shareText,
        summaryLists,
        playedList,
        notPlayedList,
        completionCountEl,
        prevBtn,
        playedBtn,
        notPlayedBtn,
        downloadBtn,
        shareBtn,
        resetBtn,
        controlsButtons,
    ];

    if (requiredElements.some((element) => !element)) {
        return;
    }

    gameName.setAttribute('role', 'button');
    gameName.setAttribute('tabindex', '0');
    gameName.setAttribute('aria-expanded', 'false');

    const varietyData = window.VARIETY_DATA || {};
    const varietyConfig = window.VARIETY_CONFIG || {};
    const displayGameCount = Number.isFinite(Number(varietyConfig.displayGameCount))
        ? Math.max(1, Number(varietyConfig.displayGameCount))
        : 50;
    const fullGames = Array.isArray(varietyData.games) ? varietyData.games : [];
    const getGameName = (game) => (game && typeof game === 'object' ? game.name : game);
    const getGameThumbnail = (game) => (game && typeof game === 'object' ? game.thumbnail : null);
    const placeholderThumbnail = 'assets/misc/placeholder.svg';
    const swipeThreshold = 50;
    const swipeMaxTimeMs = 600;
    const swipeMaxOffset = 24;
    const swipeDampen = 0.2;
    let touchStartX = null;
    let touchStartY = null;
    let touchStartTime = null;
    let isDraggingCard = false;
    let downloadErrorTimeout = null;
    const expandedGameNames = new Set();

    const formatGameName = (name) => {
        if (!name) {
            return '';
        }
        const withoutYear = name.replace(/\s*\(\d{4}\)\s*/g, ' ');
        return withoutYear.replace(/\s{2,}/g, ' ').trim();
    };

    const setThumbnailSwipeOffset = (offset) => {
        if (!gameThumbnailPicture) {
            return;
        }
        gameThumbnailPicture.style.transform = offset ? `translateX(${offset}px)` : '';
    };

    const resetThumbnailSwipe = () => {
        if (!gameThumbnailPicture) {
            return;
        }
        gameThumbnailPicture.classList.remove('game-card__thumb-frame--dragging');
        setThumbnailSwipeOffset(0);
    };

    const safeParse = (value, fallback) => {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    };

    const storage = (() => {
        try {
            const testKey = '__varietyTest__';
            window.localStorage.setItem(testKey, testKey);
            window.localStorage.removeItem(testKey);
            return window.localStorage;
        } catch (error) {
            return null;
        }
    })();

    const requestPersistentStorage = () => {
        if (!navigator.storage || !navigator.storage.persisted || !navigator.storage.persist) {
            return;
        }
        navigator.storage
            .persisted()
            .then((persisted) => (persisted ? persisted : navigator.storage.persist()))
            .catch(() => false);
    };

    const getStoredValue = (key) => {
        if (!storage) {
            return null;
        }
        try {
            return storage.getItem(key);
        } catch (error) {
            return null;
        }
    };

    const setStoredValue = (key, value) => {
        if (!storage) {
            return;
        }
        try {
            storage.setItem(key, value);
        } catch (error) {
            // Ignore storage write failures.
        }
    };

    const removeStoredValue = (key) => {
        if (!storage) {
            return;
        }
        try {
            storage.removeItem(key);
        } catch (error) {
            // Ignore storage removal failures.
        }
    };

    const shuffleOrder = (order) => {
        const shuffled = [...order];
        for (let index = shuffled.length - 1; index > 0; index -= 1) {
            const swapIndex = Math.floor(Math.random() * (index + 1));
            [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
        }
        return shuffled;
    };

    const completionCountKey = 'varietyCompletionCount';
    const legacySelections = safeParse(getStoredValue('varietySelections'), null);
    const storedState = safeParse(getStoredValue('varietyState'), {});

    const normalizeSelection = (value) => {
        if (value === true || value === 'true' || value === 'played') {
            return true;
        }
        if (value === false || value === 'false' || value === 'notPlayed') {
            return false;
        }
        return null;
    };

    const getDisplayCount = () => Math.min(displayGameCount, fullGames.length);
    const createDefaultOrder = (count) => Array.from({ length: count }, (_, index) => index);
    const createRandomGameIds = () => shuffleOrder(createDefaultOrder(fullGames.length)).slice(0, getDisplayCount());
    const hasValidGameIds = (value) =>
        Array.isArray(value)
        && value.length === getDisplayCount()
        && value.every((id) => Number.isInteger(id) && id >= 0 && id < fullGames.length);

    let gameIds = hasValidGameIds(storedState.gameIds) ? storedState.gameIds : createRandomGameIds();
    let games = gameIds.map((id) => fullGames[id]);
    let totalGames = games.length;
    let completionCount = Number.parseInt(getStoredValue(completionCountKey), 10);
    if (!Number.isFinite(completionCount) || completionCount < 0) {
        completionCount = 0;
    }
    let completionRecorded = storedState.completionRecorded === true;
    let canGoBack = storedState.canGoBack === true;
    const selections = Array.isArray(storedState.selections) && storedState.selections.length === totalGames
        ? storedState.selections.map(normalizeSelection)
        : Array.isArray(legacySelections) && legacySelections.length === totalGames
            ? games.map((_, index) => (legacySelections.includes(index) ? true : null))
            : Array.from({ length: totalGames }, () => null);

    let currentIndex = Number.isInteger(storedState.currentIndex) ? storedState.currentIndex : 0;
    if (currentIndex < 0 || currentIndex > totalGames) {
        currentIndex = 0;
    }
    let gameOrder = Array.isArray(storedState.gameOrder) && storedState.gameOrder.length === totalGames
        ? storedState.gameOrder
        : createDefaultOrder(totalGames);

    const saveState = () => {
        setStoredValue(
            'varietyState',
            JSON.stringify({
                gameIds,
                selections,
                currentIndex,
                gameOrder,
                canGoBack,
                completionRecorded,
            })
        );
    };

    const resetSessionGames = () => {
        gameIds = createRandomGameIds();
        games = gameIds.map((id) => fullGames[id]);
        totalGames = games.length;
        selections.length = 0;
        selections.push(...Array.from({ length: totalGames }, () => null));
        currentIndex = 0;
        gameOrder = shuffleOrder(createDefaultOrder(totalGames));
        expandedGameNames.clear();
        canGoBack = false;
        completionRecorded = false;
    };

    const getPlayedCount = () => selections.filter((value) => value === true).length;
    const getNotPlayedCount = () => selections.filter((value) => value === false).length;
    const getDecidedCount = () => selections.filter((value) => value === true || value === false).length;
    const getScorePercent = () => (totalGames > 0 ? Math.round((getPlayedCount() / totalGames) * 100) : 0);
    const formatCountLabel = (count, total) => `${count}/${total}`;
    const getProgressPercent = (viewedCount) => {
        if (totalGames <= 0) {
            return 0;
        }
        const clampedCount = Math.max(0, Math.min(viewedCount, totalGames));
        return Math.round((clampedCount / totalGames) * 100);
    };

    const loadTaglines = async () => {
        try {
            const response = await fetch('assets/misc/taglines.json', { cache: 'no-store' });
            if (!response.ok) {
                return null;
            }
            const payload = await response.json();
            if (!payload || typeof payload !== 'object' || !payload.tiers || typeof payload.tiers !== 'object') {
                return null;
            }
            return payload.tiers;
        } catch (error) {
            return null;
        }
    };

    const taglines = (await loadTaglines()) || {};

    const getTagline = (score) => {
        const tiers = taglines;

        const thresholds = Object.keys(tiers)
            .map((value) => Number(value))
            .sort((a, b) => b - a);

        for (const threshold of thresholds) {
            if (score >= threshold) {
                const options = tiers[threshold];
                return options[Math.floor(Math.random() * options.length)];
            }
        }

        return '';
    };

    const getShareText = (playedCount, totalCount, tagline) =>
        `I scored ${formatCountLabel(playedCount, totalCount)} on the Variety Game Score 🎮\n${tagline}\n\nTry it yourself 👉 https://pcgamers.win/varietygamerscore`;

    let finalTagline = null;

    const updateCounter = () => {
        const count = getPlayedCount();
        const notPlayed = getNotPlayedCount();
        selectedCount.textContent = count;
        notPlayedCount.textContent = notPlayed;
        scorePercent.textContent = formatCountLabel(count, totalGames);
    };

    const updateCompletionCount = () => {
        completionCountEl.textContent = completionCount;
    };

    const recordCompletionIfNeeded = () => {
        if (completionRecorded || totalGames <= 0) {
            return;
        }
        if (getDecidedCount() >= totalGames) {
            completionRecorded = true;
            completionCount += 1;
            setStoredValue(completionCountKey, `${completionCount}`);
            updateCompletionCount();
            saveState();
        }
    };

    const updateGameProgress = (viewedCount) => {
        const percent = getProgressPercent(viewedCount);
        gameProgressBar.style.width = `${percent}%`;
        gameProgress.setAttribute('aria-valuenow', `${percent}`);
    };

    const updateControlsVisibility = (viewedCount) => {
        const percent = getProgressPercent(viewedCount);
        controlsButtons.hidden = percent !== 100;
    };

    const updateFinalListsVisibility = (isVisible) => {
        summaryLists.hidden = !isVisible;
        if (!isVisible) {
            playedList.innerHTML = '';
            notPlayedList.innerHTML = '';
        }
    };

    const updateFinalLists = () => {
        const playedItems = [];
        const notPlayedItems = [];
        selections.forEach((selection, index) => {
            if (selection !== true && selection !== false) {
                return;
            }
            const gameIndex = gameOrder[index];
            if (!Number.isInteger(gameIndex) || !games[gameIndex]) {
                return;
            }
            const gameEntry = games[gameIndex];
            const gameNameValue = getGameName(gameEntry) || 'Unknown game';
            const gameThumbnail = getGameThumbnail(gameEntry);
            const thumbnailSrc = gameThumbnail && gameThumbnail.fallback ? gameThumbnail.fallback : placeholderThumbnail;
            if (selection === true) {
                playedItems.push({ name: gameNameValue, thumbnail: thumbnailSrc });
            } else {
                notPlayedItems.push({ name: gameNameValue, thumbnail: thumbnailSrc });
            }
        });

        const renderItems = (items, target) => {
            target.innerHTML = '';
            items.forEach((item) => {
                const listItem = document.createElement('li');
                listItem.className = 'summary-list__item';
                const displayName = formatGameName(item.name);
                const thumbnail = document.createElement('img');
                thumbnail.className = 'summary-list__thumb';
                thumbnail.src = item.thumbnail;
                thumbnail.alt = `${displayName} thumbnail`;
                thumbnail.loading = 'lazy';
                thumbnail.decoding = 'async';
                listItem.title = displayName;
                listItem.appendChild(thumbnail);
                target.appendChild(listItem);
            });
        };

        renderItems(playedItems, playedList);
        renderItems(notPlayedItems, notPlayedList);
    };

    const updateStatus = (selection) => {
        if (selection === true) {
            gameStatus.textContent = 'Marked: Played.';
        } else if (selection === false) {
            gameStatus.textContent = 'Marked: Not Played.';
        } else {
            gameStatus.textContent = 'Mark your progress to keep score.';
        }
    };

    const canToggleGameName = () => currentIndex < games.length && games.length > 0;

    const toggleGameNameExpansion = () => {
        if (!canToggleGameName()) {
            return;
        }
        const gameIndex = gameOrder[currentIndex];
        if (!Number.isInteger(gameIndex)) {
            return;
        }
        if (expandedGameNames.has(gameIndex)) {
            expandedGameNames.delete(gameIndex);
        } else {
            expandedGameNames.add(gameIndex);
        }
        const currentGame = games[gameIndex];
        const currentName = getGameName(currentGame) || 'Unknown game';
        const isExpanded = expandedGameNames.has(gameIndex);
        const displayName = formatGameName(currentName);
        gameName.textContent = displayName;
        gameName.title = displayName;
        gameName.setAttribute('aria-expanded', `${isExpanded}`);
        gameName.classList.toggle('game-card__name--expanded', isExpanded);
    };

    const renderGame = () => {
        const thumbnailPicture = document.getElementById('gameThumbnailPicture');
        const thumbnailSource = document.getElementById('gameThumbnailWebp');
        const thumbnailEl = document.getElementById('gameThumbnail');
        const clearThumbnail = () => {
            if (!thumbnailEl || !thumbnailPicture) {
                return;
            }
            thumbnailEl.src = '';
            thumbnailEl.alt = '';
            if (thumbnailSource) {
                thumbnailSource.removeAttribute('srcset');
            }
            thumbnailPicture.hidden = true;
        };

        if (games.length === 0) {
            gameName.textContent = 'No games available.';
            gameName.title = '';
            gameName.setAttribute('aria-expanded', 'false');
            gameName.setAttribute('aria-disabled', 'true');
            gameName.classList.remove('game-card__name--expanded');
            gameStatus.textContent = 'Please check back later.';
            shareText.hidden = true;
            shareBtn.disabled = true;
            downloadBtn.disabled = true;
            updateGameProgress(0);
            updateControlsVisibility(0);
            updateFinalListsVisibility(false);
            clearThumbnail();
            prevBtn.disabled = true;
            playedBtn.disabled = true;
            notPlayedBtn.disabled = true;
            return;
        }

        if (currentIndex >= games.length) {
            const percent = getScorePercent();
            if (!finalTagline) {
                finalTagline = getTagline(percent);
            }
            gameName.textContent = 'Start over to rate again.';
            gameName.title = '';
            gameName.setAttribute('aria-expanded', 'false');
            gameName.setAttribute('aria-disabled', 'true');
            gameName.classList.remove('game-card__name--expanded');
            gameStatus.textContent = 'Thank you for playing.' || 'Nice work.';
            shareText.textContent = getShareText(getPlayedCount(), totalGames, finalTagline || 'Nice work!');
            shareText.hidden = false;
            shareBtn.disabled = false;
            const progressPercent = getProgressPercent(games.length);
            const isExportReady = progressPercent === 100 && gameName.textContent === 'Start over to rate again.';
            downloadBtn.disabled = !isExportReady;
            updateGameProgress(games.length);
            updateControlsVisibility(games.length);
            updateFinalLists();
            updateFinalListsVisibility(true);
            clearThumbnail();
            prevBtn.disabled = currentIndex === 0 || !canGoBack;
            playedBtn.disabled = true;
            notPlayedBtn.disabled = true;
            recordCompletionIfNeeded();
            return;
        }

        const currentGame = games[gameOrder[currentIndex]];
        const currentName = getGameName(currentGame) || 'Unknown game';
        const currentThumbnail = getGameThumbnail(currentGame);
        const gameIndex = gameOrder[currentIndex];
        const isExpanded = expandedGameNames.has(gameIndex);
        const displayName = formatGameName(currentName);
        gameName.textContent = displayName;
        gameName.title = displayName;
        gameName.setAttribute('aria-expanded', `${isExpanded}`);
        gameName.setAttribute('aria-disabled', 'false');
        gameName.classList.toggle('game-card__name--expanded', isExpanded);
        if (thumbnailEl && thumbnailPicture) {
            const fallbackSrc = currentThumbnail && currentThumbnail.fallback ? currentThumbnail.fallback : placeholderThumbnail;
            const webpSrc = currentThumbnail && currentThumbnail.webp ? currentThumbnail.webp : '';
            thumbnailPicture.hidden = true;
            thumbnailEl.removeAttribute('src');
            thumbnailEl.alt = '';
            if (thumbnailSource) {
                thumbnailSource.removeAttribute('srcset');
            }
            if (webpSrc && thumbnailSource) {
                thumbnailSource.srcset = webpSrc;
            }
            thumbnailEl.src = fallbackSrc;
            thumbnailEl.alt = `${currentName} thumbnail`;
            thumbnailPicture.hidden = false;
        }
        prevBtn.disabled = currentIndex === 0 || !canGoBack;
        playedBtn.disabled = false;
        notPlayedBtn.disabled = false;
        shareText.hidden = true;
        shareBtn.disabled = true;
        downloadBtn.disabled = true;
        const decidedCount = getDecidedCount();
        updateGameProgress(decidedCount);
        updateControlsVisibility(decidedCount);
        updateFinalListsVisibility(false);
        updateStatus(selections[currentIndex]);
        recordCompletionIfNeeded();
    };

    const advance = (playedValue) => {
        selections[currentIndex] = playedValue;
        if (currentIndex < games.length) {
            currentIndex += 1;
        }
        canGoBack = currentIndex > 0;
        updateCounter();
        saveState();
        renderGame();
    };

    const goBack = () => {
        if (currentIndex === 0 || !canGoBack) {
            return;
        }
        currentIndex -= 1;
        canGoBack = false;
        saveState();
        renderGame();
    };

    const showDownloadError = (message) => {
        const fallbackMessage = 'Scorecard export is unavailable right now. Please try again soon.';
        const previousStatus = gameStatus.textContent;
        gameStatus.textContent = message || fallbackMessage;
        if (downloadErrorTimeout) {
            clearTimeout(downloadErrorTimeout);
        }
        downloadErrorTimeout = window.setTimeout(() => {
            gameStatus.textContent = previousStatus;
            downloadErrorTimeout = null;
        }, 4000);
    };

    const downloadScorecard = async () => {
        const percent = getScorePercent();
        const tagline = finalTagline || getTagline(percent);
        const playedCount = getPlayedCount();
        const url = new URL('scorecard.php', window.location.href);
        url.searchParams.set('played', playedCount);
        url.searchParams.set('total', totalGames);
        if (tagline) {
            url.searchParams.set('tagline', tagline);
        }

        const randomName = Math.random().toString(36).substring(7);
        downloadBtn.disabled = true;

        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin' });
            const contentType = response.headers.get('Content-Type') || '';
            if (!response.ok || !contentType.includes('image/')) {
                const errorText = await response.text();
                showDownloadError(errorText.trim());
                return;
            }

            const blob = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.download = `varietyScore_${randomName}.png`;
            downloadLink.href = blobUrl;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            window.URL.revokeObjectURL(blobUrl);
        } catch (error) {
            showDownloadError();
        } finally {
            downloadBtn.disabled = false;
        }
    };

    const copyShareText = () => {
        const percent = getScorePercent();
        const tagline = finalTagline || getTagline(percent);
        const text = getShareText(getPlayedCount(), totalGames, tagline || 'Nice work!');
        const setButtonLabel = (label) => {
            shareBtn.textContent = label;
            setTimeout(() => {
                shareBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy share text';
            }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard
                .writeText(text)
                .then(() => setButtonLabel('Copied!'))
                .catch(() => setButtonLabel('Copy failed'));
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            setButtonLabel('Copied!');
        } catch (error) {
            setButtonLabel('Copy failed');
        } finally {
            document.body.removeChild(textarea);
        }
    };

    playedBtn.addEventListener('click', () => advance(true));
    notPlayedBtn.addEventListener('click', () => advance(false));
    prevBtn.addEventListener('click', goBack);
    gameName.addEventListener('click', toggleGameNameExpansion);
    gameName.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleGameNameExpansion();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented) {
            return;
        }
        if (event.key === 'ArrowLeft') {
            if (!notPlayedBtn.disabled) {
                event.preventDefault();
                advance(false);
            }
        } else if (event.key === 'ArrowRight') {
            if (!playedBtn.disabled) {
                event.preventDefault();
                advance(true);
            }
        }
    });
    document.addEventListener(
        'touchstart',
        (event) => {
            if (event.touches.length !== 1) {
                touchStartX = null;
                touchStartY = null;
                touchStartTime = null;
                isDraggingCard = false;
                return;
            }
            const touch = event.touches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
            touchStartTime = Date.now();
            isDraggingCard = true;
            if (gameThumbnailPicture) {
                gameThumbnailPicture.classList.add('game-card__thumb-frame--dragging');
            }
        },
        { passive: true }
    );
    document.addEventListener(
        'touchmove',
        (event) => {
            if (!isDraggingCard || touchStartX === null || touchStartY === null) {
                return;
            }
            const touch = event.touches[0];
            const deltaX = touch.clientX - touchStartX;
            const deltaY = touch.clientY - touchStartY;
            if (Math.abs(deltaX) < Math.abs(deltaY)) {
                setThumbnailSwipeOffset(0);
                return;
            }
            const dampenedOffset = Math.max(
                -swipeMaxOffset,
                Math.min(swipeMaxOffset, deltaX * swipeDampen)
            );
            setThumbnailSwipeOffset(dampenedOffset);
        },
        { passive: true }
    );
    document.addEventListener(
        'touchend',
        (event) => {
            if (touchStartX === null || touchStartY === null || touchStartTime === null) {
                resetThumbnailSwipe();
                return;
            }
            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - touchStartX;
            const deltaY = touch.clientY - touchStartY;
            const elapsed = Date.now() - touchStartTime;
            touchStartX = null;
            touchStartY = null;
            touchStartTime = null;
            isDraggingCard = false;
            resetThumbnailSwipe();

            if (elapsed > swipeMaxTimeMs) {
                return;
            }

            if (Math.abs(deltaX) < swipeThreshold || Math.abs(deltaX) < Math.abs(deltaY)) {
                return;
            }

            if (deltaX > 0) {
                if (!playedBtn.disabled) {
                    advance(true);
                }
            } else if (!notPlayedBtn.disabled) {
                advance(false);
            }
        },
        { passive: true }
    );
    document.addEventListener(
        'touchcancel',
        () => {
            touchStartX = null;
            touchStartY = null;
            touchStartTime = null;
            isDraggingCard = false;
            resetThumbnailSwipe();
        },
        { passive: true }
    );

    downloadBtn.addEventListener('click', downloadScorecard);
    shareBtn.addEventListener('click', copyShareText);
    resetBtn.addEventListener('click', () => {
        removeStoredValue('varietySelections');
        removeStoredValue('varietyState');
        resetSessionGames();
        finalTagline = null;
        shareText.hidden = true;
        shareBtn.disabled = true;
        updateFinalListsVisibility(false);
        updateCounter();
        updateCompletionCount();
        saveState();
        renderGame();
    });

    updateCounter();
    updateCompletionCount();
    renderGame();
    requestPersistentStorage();

};

document.addEventListener('DOMContentLoaded', init);
