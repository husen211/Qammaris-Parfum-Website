# Consent UI correction

Owner requested removal of the floating “Pilihan analytics” button after Allow/Skip. The same preference control now participates in the ordinary footer flow. The consent banner still closes and the browser choice remains saved; withdrawal stays accessible without covering the catalog or homepage. No loader, event, tracking scope, config, schema, order, customer or media changes.

Checks: six existing PHP analytics tests /81 assertions passed; production Vite build passed. Chrome actual inner viewports390x844 and1440x900 verified no horizontal overflow, footer ancestry, computed static positioning, keyboard reopening/focus, banner dismissal and remembered refusal/no Google script after reload. Physical Safari/Android and comparative performance measurements unavailable. GA4 still adds network/CPU work after consent; no performance improvement from this visual correction is claimed.

| View | Before | After |
|---|---|---|
| Mobile390x844 | [before](consent-before-mobile.png) | [after](consent-after-mobile.png) |
| Desktop1440x900 | [before](consent-before-desktop.png) | [after](consent-after-desktop.png) |

[Mobile footer](consent-footer-mobile.png). Browser screenshot pixel dimensions can differ from inner viewport dimensions due to Chrome capture scaling/scrollbars.

Release gates: PR CI, official production deployment and live footer/no-overlay checks. Revert only this layout/partial placement to roll back; database/config rollback is unnecessary. Runbook updated with the footer location.
