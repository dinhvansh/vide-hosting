"use client";

import { useCallback, useEffect, useState } from "react";

export function useSectionNavigation(sectionIds: readonly string[]) {
  const [activeSection, setActiveSection] = useState(sectionIds[0]);

  useEffect(() => {
    let frame = 0;
    const updateActiveSection = () => {
      frame = 0;
      const sections = sectionIds
        .map((id) => document.getElementById(id))
        .filter((section): section is HTMLElement => Boolean(section));
      if (!sections.length) return;

      const activationLine = 150;
      let current = sections[0].id;
      for (const section of sections) {
        if (section.getBoundingClientRect().top <= activationLine) {
          current = section.id;
        } else {
          break;
        }
      }
      setActiveSection(current);
    };
    const scheduleUpdate = () => {
      if (!frame) frame = window.requestAnimationFrame(updateActiveSection);
    };

    updateActiveSection();
    window.addEventListener("scroll", scheduleUpdate, { passive: true });
    window.addEventListener("resize", scheduleUpdate);
    return () => {
      window.removeEventListener("scroll", scheduleUpdate);
      window.removeEventListener("resize", scheduleUpdate);
      if (frame) window.cancelAnimationFrame(frame);
    };
  }, [sectionIds]);

  const navigateToSection = useCallback((sectionId: string) => {
    const section = document.getElementById(sectionId);
    if (!section) return;
    setActiveSection(sectionId);
    window.history.replaceState(
      window.history.state,
      "",
      `${window.location.pathname}${window.location.search}#${sectionId}`,
    );
    section.scrollIntoView({ behavior: "smooth", block: "start" });
  }, []);

  return { activeSection, navigateToSection };
}
