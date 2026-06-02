/**
 * Contact Page plugin
 *
 * Registriert die "contact_page" Collection mit allen editierbaren
 * Feldern für die Kontaktseite der Radiologie Baden-Baden.
 *
 * Felder:
 *  - heading / subheading (Hero-Text)
 *  - praxis_name, telefon, email
 *  - adresse (mehrzeilig)
 *  - oeffnungszeiten (mehrzeilig, eine Zeile pro Eintrag)
 */

import { definePlugin } from "emdash";
import type { PluginDefinition } from "emdash";

const definition: PluginDefinition = {
	id: "contact-page",
	version: "0.1.0",

	admin: {
		collections: [
			{
				type: "contact_page",
				label: "Kontaktseite",
				description: "Inhalte der Kontakt-Seite bearbeiten",
				singleton: true,
				fields: [
					// ── Hero ──────────────────────────────────────────
					{
						type: "text_input",
						action_id: "subheading",
						label: "Subheading (über dem Titel)",
						placeholder: "Wir sind für Sie da",
					},
					{
						type: "text_input",
						action_id: "heading",
						label: "Hauptüberschrift",
						placeholder: "Kontaktieren Sie uns",
					},

					// ── Kontaktdaten ──────────────────────────────────
					{
						type: "text_input",
						action_id: "praxis_name",
						label: "Praxisname",
						placeholder: "Radiologie Baden-Baden",
					},
					{
						type: "text_input",
						action_id: "telefon",
						label: "Telefon",
						placeholder: "07221 30097-0",
					},
					{
						type: "text_input",
						action_id: "email",
						label: "E-Mail-Adresse",
						placeholder: "info@radiologie-baden-baden.de",
					},

					// ── Adresse ───────────────────────────────────────
					{
						type: "text_input",
						action_id: "adresse",
						label: "Adresse (eine Zeile pro Eintrag)",
						multiline: true,
						placeholder: "Beethovenstraße 2\n76530 Baden-Baden",
					},

					// ── Öffnungszeiten ────────────────────────────────
					{
						type: "text_input",
						action_id: "oeffnungszeiten",
						label: "Öffnungszeiten (eine Zeile pro Eintrag)",
						multiline: true,
						placeholder: "Mo–Fr: 08:00 – 18:00 Uhr\nSa: 09:00 – 13:00 Uhr",
					},
				],
			},
		],
	},
};

export function createPlugin() {
	return definePlugin(definition);
}

export default createPlugin;
