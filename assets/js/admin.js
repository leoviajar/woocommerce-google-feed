document.addEventListener('DOMContentLoaded', function() {
    var generateButton = document.getElementById('generate-token');
    
    if (generateButton) {
        generateButton.addEventListener('click', function() {
            // Gerar token aleatório
            var token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            
            // Atualizar o campo de input
            var tokenInput = document.querySelector('input[name="security_token"]');
            if (tokenInput) {
                tokenInput.value = token;
            }
            
            // Atualizar apenas a URL exibida na página (não o menu lateral)
            var feedLink = document.getElementById('wc-google-feed-token-link');
            if (feedLink) {
                var baseUrl = feedLink.href.split('/feed')[0];
                var newUrl = baseUrl + '/feed-' + token + '.xml';
                feedLink.href = newUrl;
                feedLink.textContent = newUrl;
            }
        });
    }
});