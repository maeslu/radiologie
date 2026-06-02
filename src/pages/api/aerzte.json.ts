import type { APIRoute } from "astro";
import { getEmDashCollection } from "emdash";

export const GET: APIRoute = async () => {
  const { entries } = await getEmDashCollection("arzt");

  const aerzte = entries.map((arzt: any) => {
    const foto = arzt.data.foto?.meta?.storageKey
      ? `/_emdash/api/media/file/${arzt.data.foto.meta.storageKey}`
      : "";

    const schwerpunkte = (
      arzt.data.terms?.schwerpunkte ??
      arzt.data.taxonomies?.schwerpunkte ??
      []
    ).map((t: any) => t.label ?? t.name ?? t.title ?? t).filter(Boolean);

    return {
      slug:        arzt.slug,
      name:        arzt.data.titel ?? arzt.data.title ?? "",
      subheading:  arzt.data.subheading ?? "",
      fachrichtung: arzt.data.intro_text ?? arzt.data.fachrichtung ?? "",
      btnText:     arzt.data.mehr_erfahren ?? "Mehr erfahren",
      foto,
      schwerpunkte,
    };
  });

  return new Response(JSON.stringify(aerzte), {
    headers: { "Content-Type": "application/json" },
  });
};