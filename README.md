WordTown is a WordPress plugin that generates an isometric world of your posts.
For each post you publish, it will generate a tile.

## Development

For fully functioning dev environent, your docker needs `imagemagic` with png suport.

```
npm run wp-env run cli /bin/bash
sudo apk add --no-cache imagemagick imagemagick-dev libpng-dev
```

## Future Improvements

- Configuring the prompts
- Portals to other sites via trackbacks
- Smarter tile positioning
- Background tile
