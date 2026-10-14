const article = document.querySelector('main article');
const addBtn = article.querySelector('button');

const originals = [];
article.querySelectorAll(':scope > p').forEach((p) => originals.push(p.cloneNode(true)));

let hideImages = false;

function allParagraphs() {
  return Array.from(article.querySelectorAll(':scope > p'));
}

function shuffle(list) {
  const copy = list.slice();
  for (let i = copy.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    const tmp = copy[i];
    copy[i] = copy[j];
    copy[j] = tmp;
  }
  return copy;
}

function syncImageVisibility(p) {
  if (hideImages && p.querySelector('img')) {
    p.hidden = true;
  } else {
    p.hidden = false;
  }
}

function rebuildSeparators() {
  article.querySelectorAll(':scope > hr[data-separator]').forEach((hr) => hr.remove());
  const ps = allParagraphs();
  ps.forEach((p, i) => {
    const pairEnded = (i + 1) % 2 === 0;
    const moreAfter = i < ps.length - 1;
    if (pairEnded && moreAfter) {
      const hr = document.createElement('hr');
      hr.setAttribute('data-separator', '');
      p.after(hr);
    }
  });
}

function onAddClick() {
  const batch = shuffle(originals).map((p) => {
    const node = p.cloneNode(true);
    syncImageVisibility(node);
    return node;
  });
  allParagraphs().at(-1).after(...batch);
  rebuildSeparators();
}

const toggleBtn = document.createElement('button');
toggleBtn.type = 'button';

function toggleLabel() {
  return hideImages ? 'Show image paragraphs' : 'Hide image paragraphs';
}

toggleBtn.textContent = toggleLabel();
toggleBtn.addEventListener('click', () => {
  hideImages = !hideImages;
  allParagraphs().forEach(syncImageVisibility);
  toggleBtn.textContent = toggleLabel();
});

addBtn.after(toggleBtn);
addBtn.addEventListener('click', onAddClick);


// Initial setup. I can decide to use self running function or call it from the DOMContentLoaded event.
rebuildSeparators();
