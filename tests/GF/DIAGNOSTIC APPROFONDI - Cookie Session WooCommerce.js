/**
 * 🔬 DIAGNOSTIC APPROFONDI - Cookie Session WooCommerce
 * 
 * Analyse pourquoi le cookie de session n'est pas accessible
 * alors que le panier contient un produit
 */

(function() {
  'use strict';
  
  console.clear();
  console.log('%c🔬 DIAGNOSTIC COOKIE SESSION WOOCOMMERCE', 'background: #d63638; color: white; padding: 8px; font-weight: bold; font-size: 14px');
  console.log('%c═══════════════════════════════════════════════════════════', 'color: #ddd');
  
  // ═══════════════════════════════════════════════════════════════
  // 1. ANALYSE DES COOKIES DISPONIBLES
  // ═══════════════════════════════════════════════════════════════
  console.log('%c\n📦 ÉTAPE 1 : Analyse complète des cookies', 'color: #2271b1; font-weight: bold');
  console.log('%c─────────────────────────────────────────', 'color: #ddd');
  
  const allCookies = document.cookie.split(';').map(c => c.trim());
  
  console.log(`\n📊 Nombre total de cookies : ${allCookies.length}`);
  console.log('\n🔍 Liste complète des cookies :');
  
  const cookieDetails = {};
  allCookies.forEach((cookie, index) => {
      const [name, value] = cookie.split('=');
      cookieDetails[name] = value ? value.substring(0, 50) + '...' : '(vide)';
      console.log(`  ${index + 1}. ${name}`);
  });
  
  // Rechercher TOUS les cookies WooCommerce
  console.log('\n🛒 Cookies WooCommerce détectés :');
  const wcCookies = allCookies.filter(c => 
      c.toLowerCase().includes('woocommerce') || 
      c.toLowerCase().includes('wc_') ||
      c.startsWith('wp_woocommerce')
  );
  
  if (wcCookies.length > 0) {
      wcCookies.forEach(cookie => {
          console.log(`%c  ✓ ${cookie.split('=')[0]}`, 'color: #00a32a');
      });
  } else {
      console.log('%c  ✗ Aucun cookie WooCommerce trouvé', 'color: #d63638; font-weight: bold');
  }
  
  // ═══════════════════════════════════════════════════════════════
  // 2. RECHERCHE SPÉCIFIQUE DU COOKIE SESSION
  // ═══════════════════════════════════════════════════════════════
  console.log('%c\n🔑 ÉTAPE 2 : Recherche cookie session', 'color: #2271b1; font-weight: bold');
  console.log('%c─────────────────────────────────────────', 'color: #ddd');
  
  // Patterns possibles pour le cookie de session
  const sessionPatterns = [
      'wp_woocommerce_session_',
      'woocommerce_session_',
      'wc_session_',
  ];
  
  let sessionCookie = null;
  let sessionPattern = null;
  
  for (const pattern of sessionPatterns) {
      sessionCookie = allCookies.find(c => c.startsWith(pattern));
      if (sessionCookie) {
          sessionPattern = pattern;
          break;
      }
  }
  
  if (sessionCookie) {
      console.log('%c✓ Cookie de session trouvé !', 'color: #00a32a; font-weight: bold');
      console.log(`  Pattern : ${sessionPattern}`);
      console.log(`  Cookie : ${sessionCookie.split('=')[0]}`);
      
      // Parser le cookie
      const cookieValue = sessionCookie.split('=')[1];
      if (cookieValue) {
          const decoded = decodeURIComponent(cookieValue);
          const parts = decoded.split('||');
          
          console.log('\n📋 Contenu du cookie :');
          console.log(`  • Customer ID (session_key) : ${parts[0]}`);
          if (parts[1]) console.log(`  • Expiration : ${new Date(parseInt(parts[1]) * 1000).toLocaleString()}`);
          if (parts[2]) console.log(`  • HMAC : ${parts[2].substring(0, 20)}...`);
          if (parts[3]) console.log(`  • Token : ${parts[3].substring(0, 20)}...`);
      }
  } else {
      console.log('%c✗ AUCUN cookie de session trouvé', 'color: #d63638; font-weight: bold');
      console.log('\n🔍 Patterns recherchés :');
      sessionPatterns.forEach(p => console.log(`  • ${p}`));
  }
  
  // ═══════════════════════════════════════════════════════════════
  // 3. VÉRIFICATION API PANIER
  // ═══════════════════════════════════════════════════════════════
  console.log('%c\n🛒 ÉTAPE 3 : Vérification API panier', 'color: #2271b1; font-weight: bold');
  console.log('%c─────────────────────────────────────────', 'color: #ddd');
  
  fetch('/wp-json/wc/store/v1/cart', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
          'Content-Type': 'application/json',
      }
  })
  .then(response => {
      console.log(`\n📡 Statut API : ${response.status} ${response.statusText}`);
      
      // Analyser les headers de réponse
      console.log('\n📨 Headers de réponse (Set-Cookie) :');
      const setCookieHeaders = response.headers.get('Set-Cookie');
      if (setCookieHeaders) {
          console.log(setCookieHeaders);
      } else {
          console.log('%c  ⚠️ Aucun header Set-Cookie dans la réponse', 'color: #dba617');
      }
      
      return response.json();
  })
  .then(cart => {
      console.log('\n✓ Réponse API panier :');
      console.log(`  • Items : ${cart.items?.length || 0}`);
      console.log(`  • Total : ${cart.totals?.total_price || 'N/A'}`);
      
      if (cart.items && cart.items.length > 0) {
          console.log('\n📦 Produits dans le panier :');
          cart.items.forEach((item, idx) => {
              console.log(`  ${idx + 1}. ${item.name} (ID: ${item.id})`);
          });
          
          console.log('\n%c✓ LE PANIER CONTIENT DES PRODUITS', 'color: #00a32a; font-weight: bold; font-size: 13px');
          console.log('%c→ La session DEVRAIT exister côté serveur', 'color: #00a32a');
      }
      
      // ═══════════════════════════════════════════════════════════════
      // 4. DIAGNOSTIC DU PROBLÈME
      // ═══════════════════════════════════════════════════════════════
      console.log('%c\n🎯 ÉTAPE 4 : Diagnostic du problème', 'color: #2271b1; font-weight: bold');
      console.log('%c═════════════════════════════════════════', 'color: #ddd');
      
      if (!sessionCookie && cart.items && cart.items.length > 0) {
          console.log('%c\n❌ PROBLÈME IDENTIFIÉ', 'background: #d63638; color: white; padding: 4px 8px; font-weight: bold');
          console.log('\n📊 État actuel :');
          console.log('%c  ✓ Panier contient des produits', 'color: #00a32a');
          console.log('%c  ✗ Cookie de session ABSENT en JavaScript', 'color: #d63638');
          
          console.log('\n🔬 Causes possibles :');
          console.log('%c\n1. Cookie HttpOnly (le plus probable)', 'color: #2271b1; font-weight: bold');
          console.log('   → WooCommerce a créé le cookie avec le flag HttpOnly');
          console.log('   → Le cookie existe mais n\'est PAS accessible en JavaScript');
          console.log('   → Il est uniquement accessible côté serveur (PHP)');
          console.log('   → C\'est une mesure de sécurité normale');
          
          console.log('%c\n2. Cookie SameSite=Strict', 'color: #2271b1; font-weight: bold');
          console.log('   → Le cookie n\'est pas envoyé dans certains contextes');
          
          console.log('%c\n3. Cookie Secure + HTTP', 'color: #2271b1; font-weight: bold');
          console.log('   → Le cookie nécessite HTTPS mais le site est en HTTP');
          
          console.log('%c\n4. Domaine du cookie différent', 'color: #2271b1; font-weight: bold');
          console.log('   → Le cookie est défini pour un domaine parent/enfant différent');
          
          // Vérification HTTPS
          const isHttps = window.location.protocol === 'https:';
          console.log(`\n🔒 Protocole actuel : ${window.location.protocol}`);
          if (!isHttps) {
              console.log('%c  ⚠️ Site en HTTP (pas HTTPS)', 'color: #dba617');
              console.log('     → Si cookie Secure=true, il ne sera pas envoyé');
          }
          
          // Solution
          console.log('%c\n✅ SOLUTION POUR TON CAS', 'background: #00a32a; color: white; padding: 4px 8px; font-weight: bold');
          console.log('\n🎯 Le plugin PHP PEUT récupérer la session :');
          console.log('   1. Le panier existe → session existe côté serveur');
          console.log('   2. ProgressStorage::start() récupère la session via PHP');
          console.log('   3. La session_id est stockée dans wp_wcqf_progress');
          console.log('   4. YousignIframeHandler lit depuis la BDD, PAS depuis le cookie JS');
          
          console.log('\n📝 Action requise :');
          console.log('%c   → Tester avec le script PHP côté serveur', 'color: #00a32a; font-weight: bold');
          console.log('   → Le test JS ne peut PAS voir les cookies HttpOnly');
          console.log('   → C\'est NORMAL et n\'empêche PAS le plugin de fonctionner');
          
      } else if (sessionCookie) {
          console.log('%c\n✅ TOUT FONCTIONNE CORRECTEMENT', 'background: #00a32a; color: white; padding: 4px 8px; font-weight: bold');
      } else {
          console.log('%c\n⚠️ PANIER VIDE', 'background: #dba617; color: white; padding: 4px 8px; font-weight: bold');
          console.log('\n→ Ajouter un produit au panier pour créer la session');
      }
      
      // ═══════════════════════════════════════════════════════════════
      // 5. SCRIPT PHP DE VÉRIFICATION
      // ═══════════════════════════════════════════════════════════════
      console.log('%c\n🔧 ÉTAPE 5 : Test recommandé', 'color: #2271b1; font-weight: bold');
      console.log('%c═════════════════════════════════════════', 'color: #ddd');
      
      console.log('\n📋 Exécuter ce code PHP via WP-CLI ou Code Snippets :');
      console.log('%c\n───────────────────────────────────────────', 'color: #ddd');
      const phpCode = `<?php
// Test rapide de récupération session WooCommerce
if ( function_exists('WC') && WC()->session ) {
  $session_id = WC()->session->get_customer_id();
  echo "Session ID : " . $session_id . "\\n";
  
  // Vérifier le panier
  $cart_items = WC()->cart->get_cart();
  echo "Produits dans le panier : " . count($cart_items) . "\\n";
  
  foreach ($cart_items as $item) {
      echo "  - Produit ID : " . $item['product_id'] . "\\n";
  }
} else {
  echo "WooCommerce session non disponible\\n";
}`;
      
      console.log(phpCode);
      console.log('%c───────────────────────────────────────────', 'color: #ddd');
      
      console.log('\n💡 Commande DDEV :');
      console.log('%cddev wp eval "if (function_exists(\'WC\') && WC()->session) { echo \'Session: \' . WC()->session->get_customer_id(); }"', 
          'background: #f0f0f1; padding: 4px; font-family: monospace; color: #1e1e1e');
      
  })
  .catch(error => {
      console.log('%c\n✗ Erreur API panier', 'color: #d63638; font-weight: bold');
      console.error(error);
  });
  
})();