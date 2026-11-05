export function initAdminNavigation() {
  const navigation = document.querySelector('[data-admin-navigation]');
  if (!navigation) return;
  const scrollArea = navigation.querySelector('.admin-nav-sections');
  const scrollFrame = navigation.querySelector('[data-admin-nav-scroll-frame]');
  let scrollTimer;
  const updateScrollCues = () => {
    if (!scrollArea || !scrollFrame) return;
    scrollFrame.classList.toggle('has-scroll-above', scrollArea.scrollTop > 1);
    scrollFrame.classList.toggle('has-scroll-below', scrollArea.scrollHeight - scrollArea.clientHeight - scrollArea.scrollTop > 1);
  };
  if (scrollArea && scrollFrame) {
    scrollArea.addEventListener('scroll', () => {
      updateScrollCues();
      scrollFrame.classList.add('is-scrolling');
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(() => scrollFrame.classList.remove('is-scrolling'), 900);
    }, { passive: true });
    const observer = new ResizeObserver(updateScrollCues);
    observer.observe(scrollArea);
    for (const child of scrollArea.children) observer.observe(child);
    document.fonts.ready.then(updateScrollCues);
    updateScrollCues();
  }
}
